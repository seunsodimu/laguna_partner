<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

try {
    $db = Database::getInstance();
    
    echo "Starting migration: Convert global invoice_number uniqueness to per-vendor...\n\n";
    
    $query = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
              WHERE TABLE_NAME = 'invoices' AND COLUMN_NAME = 'invoice_number' AND CONSTRAINT_NAME != 'PRIMARY'";
    
    $result = $db->fetchAll($query);
    
    if (!empty($result)) {
        $constraintName = $result[0]['CONSTRAINT_NAME'];
        echo "Found existing constraint: $constraintName\n";
        
        $dropQuery = "ALTER TABLE invoices DROP KEY $constraintName";
        $db->query($dropQuery);
        echo "✓ Dropped old global unique constraint\n";
    } else {
        echo "No existing global unique constraint found (may have been removed already)\n";
    }
    
    $checkNewConstraint = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                          WHERE TABLE_NAME = 'invoices' AND CONSTRAINT_NAME = 'unique_invoice_per_vendor'";
    
    $newConstraintExists = $db->fetchAll($checkNewConstraint);
    
    if (empty($newConstraintExists)) {
        $addQuery = "ALTER TABLE invoices ADD UNIQUE KEY unique_invoice_per_vendor (invoice_number, vendor_id)";
        $db->query($addQuery);
        echo "✓ Added new per-vendor unique constraint\n";
    } else {
        echo "✓ Per-vendor unique constraint already exists\n";
    }
    
    echo "\n✓ Migration completed successfully!\n";
    echo "Invoice numbers are now unique per vendor. Multiple vendors can have the same invoice number.\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
