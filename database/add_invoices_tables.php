<?php
/**
 * Migration: Add Invoice Management Tables
 * Adds tables for invoice, line items, notes, attachments, and payments
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

try {
    $db = Database::getInstance();
    
    $queries = [
        "CREATE TABLE IF NOT EXISTS `invoices` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoice_number` VARCHAR(100) NOT NULL,
            `vendor_id` INT NOT NULL,
            `vendor_name` VARCHAR(255),
            `invoice_type` ENUM('down_payment', 'regular') DEFAULT 'regular',
            `po_id` INT,
            `invoice_date` DATE NOT NULL,
            `due_date` DATE,
            `amount_total` DECIMAL(12, 2) NOT NULL,
            `currency` VARCHAR(10) DEFAULT 'USD',
            `status` VARCHAR(50) DEFAULT 'draft',
            `payment_terms` VARCHAR(100),
            `estimated_payment_date` DATE,
            `description` TEXT,
            `notes` LONGTEXT,
            `submitted_by_user_id` INT,
            `submitted_at` TIMESTAMP NULL,
            `reviewed_by_user_id` INT,
            `reviewed_at` TIMESTAMP NULL,
            `approved_by_user_id` INT,
            `approved_at` TIMESTAMP NULL,
            `rejected_reason` LONGTEXT,
            `netsuite_id` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_invoice_per_vendor` (`invoice_number`, `vendor_id`),
            FOREIGN KEY (`vendor_id`) REFERENCES `accounts`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`approved_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE SET NULL,
            INDEX `idx_invoice_number` (`invoice_number`),
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_invoice_date` (`invoice_date`),
            INDEX `idx_netsuite_id` (`netsuite_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `invoice_line_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoice_id` INT NOT NULL,
            `line_number` INT,
            `description` TEXT,
            `quantity` DECIMAL(10, 2),
            `unit_price` DECIMAL(10, 2),
            `amount` DECIMAL(12, 2),
            `reference` VARCHAR(255),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
            INDEX `idx_invoice_id` (`invoice_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `invoice_notes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoice_id` INT NOT NULL,
            `user_id` INT,
            `user_name` VARCHAR(255),
            `user_type` VARCHAR(50),
            `note_text` LONGTEXT,
            `is_internal` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_invoice_id` (`invoice_id`),
            INDEX `idx_is_internal` (`is_internal`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `invoice_attachments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoice_id` INT NOT NULL,
            `user_id` INT,
            `file_name` VARCHAR(255) NOT NULL,
            `file_path` VARCHAR(500) NOT NULL,
            `file_size` INT,
            `file_type` VARCHAR(100),
            `attachment_type` VARCHAR(50),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_invoice_id` (`invoice_id`),
            INDEX `idx_attachment_type` (`attachment_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `payments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `invoice_id` INT NOT NULL,
            `vendor_id` INT NOT NULL,
            `payment_number` VARCHAR(100) UNIQUE,
            `payment_date` DATE NOT NULL,
            `amount` DECIMAL(12, 2) NOT NULL,
            `payment_method` ENUM('ach', 'wire', 'check', 'virtual_card') DEFAULT 'ach',
            `status` VARCHAR(50) DEFAULT 'pending',
            `currency` VARCHAR(10) DEFAULT 'USD',
            `reference_number` VARCHAR(255),
            `notes` LONGTEXT,
            `recorded_by_user_id` INT,
            `expected_arrival_date` DATE,
            `netsuite_id` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`vendor_id`) REFERENCES `accounts`(`id`) ON DELETE RESTRICT,
            FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX `idx_invoice_id` (`invoice_id`),
            INDEX `idx_vendor_id` (`vendor_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_payment_date` (`payment_date`),
            INDEX `idx_netsuite_id` (`netsuite_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($queries as $query) {
        try {
            $db->query($query);
            $tableName = extractTableName($query);
            echo "✓ Created table: $tableName\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
            $tableName = extractTableName($query);
            echo "✓ Table already exists: $tableName\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

function extractTableName($query) {
    if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $query, $matches)) {
        return $matches[1];
    }
    return 'Unknown';
}
?>
