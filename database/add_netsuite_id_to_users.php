<?php
/**
 * Migration: Add netsuite_id column to users table
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

$db = Database::getInstance();

try {
    $db->getConnection()->beginTransaction();

    $alterations = [
        "ALTER TABLE users ADD COLUMN netsuite_id INT AFTER type",
        "ALTER TABLE users ADD INDEX idx_netsuite_id (netsuite_id)"
    ];

    foreach ($alterations as $sql) {
        try {
            $db->query($sql);
            echo "✓ Executed: $sql\n";
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "✓ Already exists: $sql\n";
            } else {
                throw $e;
            }
        }
    }

    $db->getConnection()->commit();
    echo "\n✓ Migration completed successfully!\n";

} catch (\Exception $e) {
    $db->getConnection()->rollBack();
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
