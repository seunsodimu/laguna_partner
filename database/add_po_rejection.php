<?php
/**
 * Migration: Add PO Rejection Fields
 * Adds rejection_reason column to purchase_orders table
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

try {
    $db = Database::getInstance();
    
    $queries = [
        "ALTER TABLE purchase_orders ADD COLUMN rejection_reason LONGTEXT AFTER expected_factory_date",
        "ALTER TABLE purchase_orders ADD COLUMN rejected_at TIMESTAMP NULL AFTER rejection_reason"
    ];
    
    foreach ($queries as $query) {
        try {
            $db->query($query);
            echo "✓ Executed: " . substr($query, 0, 50) . "...\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
            echo "✓ Column already exists: " . substr($query, 0, 50) . "...\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
