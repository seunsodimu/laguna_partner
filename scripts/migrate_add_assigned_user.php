<?php
/**
 * Migration: Add assigned_user_id column to purchase_orders table
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use LagunaPartners\Database;

$db = Database::getInstance();

try {
    $db->getConnection()->beginTransaction();

    $alterations = [
        "ALTER TABLE purchase_orders ADD COLUMN assigned_user_id INT AFTER buyer_id",
        "ALTER TABLE purchase_orders ADD INDEX idx_assigned_user_id (assigned_user_id)",
        "ALTER TABLE purchase_orders ADD FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL"
    ];

    foreach ($alterations as $sql) {
        try {
            $db->query($sql);
            echo "✓ Executed: $sql\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                echo "✓ Already exists: $sql\n";
            } else {
                throw $e;
            }
        }
    }

    $db->getConnection()->commit();
    echo "\n✓ Migration completed successfully!\n";

} catch (\Exception $e) {
    try {
        $db->getConnection()->rollBack();
    } catch (\Exception $re) {
        // Ignore rollback errors if no active transaction
    }
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
