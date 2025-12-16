<?php
/**
 * Apply PO Fields Migration
 * Adds vessel_name, vessel_identifier, expected_factory_date columns to purchase_orders
 * Adds document_type column to po_documents
 */

require_once __DIR__ . '/../vendor/autoload.php';
use LagunaPartners\Database;

echo "=== Migration: Add PO Vessel and Factory Date Fields ===\n\n";

try {
    $db = Database::getInstance();
    
    // Check if columns already exist
    echo "Checking if columns already exist...\n";
    
    $check = $db->fetchOne("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'purchase_orders' 
          AND COLUMN_NAME = 'vessel_name'
    ");
    
    if ($check) {
        echo "✓ Columns already exist. No migration needed.\n";
        exit(0);
    }
    
    echo "\nApplying migration...\n";
    
    // Add columns to purchase_orders
    echo "\n1. Adding vessel_name column...\n";
    $db->query("ALTER TABLE `purchase_orders` ADD COLUMN `vessel_name` VARCHAR(255) AFTER `location`");
    echo "✓ vessel_name added\n";
    
    echo "\n2. Adding vessel_identifier column...\n";
    $db->query("ALTER TABLE `purchase_orders` ADD COLUMN `vessel_identifier` VARCHAR(100) AFTER `vessel_name`");
    echo "✓ vessel_identifier added\n";
    
    echo "\n3. Adding expected_factory_date column...\n";
    $db->query("ALTER TABLE `purchase_orders` ADD COLUMN `expected_factory_date` DATE AFTER `vessel_identifier`");
    echo "✓ expected_factory_date added\n";
    
    // Check if document_type column exists
    echo "\n4. Checking po_documents table...\n";
    $checkDoc = $db->fetchOne("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'po_documents' 
          AND COLUMN_NAME = 'document_type'
    ");
    
    if (!$checkDoc) {
        echo "   Adding document_type column to po_documents...\n";
        $db->query("ALTER TABLE `po_documents` ADD COLUMN `document_type` VARCHAR(50) AFTER `file_type`");
        echo "✓ document_type added\n";
        
        echo "\n5. Adding index on document_type...\n";
        $db->query("ALTER TABLE `po_documents` ADD INDEX `idx_document_type` (`document_type`)");
        echo "✓ Index created\n";
    } else {
        echo "✓ po_documents already has document_type column\n";
    }
    
    echo "\n=== Migration Completed Successfully ===\n";
    echo "\nNew columns added to purchase_orders table:\n";
    echo "  - vessel_name (VARCHAR(255))\n";
    echo "  - vessel_identifier (VARCHAR(100))\n";
    echo "  - expected_factory_date (DATE)\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}