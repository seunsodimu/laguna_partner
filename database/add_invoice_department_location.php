<?php
/**
 * Migration: Add Department, Location, and NetSuite ID to Invoices
 * Adds department_id, location_id, and netsuite_id columns to invoices and invoice_line_items tables
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

try {
    $db = Database::getInstance();
    
    $queries = [
        // Add columns to invoices table
        "ALTER TABLE invoices ADD COLUMN department_id INT AFTER netsuite_id",
        "ALTER TABLE invoices ADD COLUMN location_id INT AFTER department_id",
        "ALTER TABLE invoices ADD COLUMN netsuite_bill_id VARCHAR(255) AFTER location_id",
        
        // Add columns to invoice_line_items table
        "ALTER TABLE invoice_line_items ADD COLUMN department_id INT AFTER reference",
        "ALTER TABLE invoice_line_items ADD COLUMN location_id INT AFTER department_id"
    ];
    
    foreach ($queries as $query) {
        try {
            $db->query($query);
            echo "✓ Executed: " . substr($query, 0, 60) . "...\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
            echo "✓ Column already exists: " . substr($query, 0, 60) . "...\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
