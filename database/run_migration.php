<?php
/**
 * Migration Runner: Remove Foreign Key Constraint from purchase_orders.vendor_id
 * 
 * This script removes the foreign key constraint between purchase_orders and accounts
 * to allow purchase orders to exist for vendors that don't have portal access.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use LagunaPartners\Database;

echo "=== Migration: Remove purchase_orders.vendor_id Foreign Key ===\n\n";

try {
    $db = Database::getInstance();
    
    // Step 1: Find the constraint name
    echo "Step 1: Finding foreign key constraint...\n";
    $constraint = $db->fetchOne("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'purchase_orders' 
          AND COLUMN_NAME = 'vendor_id' 
          AND REFERENCED_TABLE_NAME = 'accounts'
        LIMIT 1
    ");
    
    if (!$constraint) {
        echo "✓ No foreign key constraint found. Already removed or never existed.\n";
        exit(0);
    }
    
    $constraintName = $constraint['CONSTRAINT_NAME'];
    echo "✓ Found constraint: {$constraintName}\n\n";
    
    // Step 2: Drop the constraint
    echo "Step 2: Dropping foreign key constraint...\n";
    $sql = "ALTER TABLE `purchase_orders` DROP FOREIGN KEY `{$constraintName}`";
    $db->query($sql);
    echo "✓ Foreign key constraint dropped successfully!\n\n";
    
    // Step 3: Verify it's removed
    echo "Step 3: Verifying removal...\n";
    $verify = $db->fetchOne("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'purchase_orders' 
          AND COLUMN_NAME = 'vendor_id' 
          AND REFERENCED_TABLE_NAME = 'accounts'
    ");
    
    if (!$verify) {
        echo "✓ Verification successful! Foreign key constraint has been removed.\n\n";
        echo "=== Migration Completed Successfully ===\n";
        echo "\nNotes:\n";
        echo "- The vendor_id column still exists and is indexed\n";
        echo "- Purchase orders can now be synced even if vendor doesn't exist in accounts table\n";
        echo "- You can identify POs without portal access by checking if vendor_id exists in accounts\n";
    } else {
        echo "✗ Warning: Constraint still exists after removal attempt.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}