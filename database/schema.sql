CREATE DATABASE IF NOT EXISTS laguna_partner;
USE laguna_partner;

-- Base Schema for Laguna Partners Portal
-- Created: 2025

-- ===== ACCOUNTS TABLE (Vendors/Dealers) =====
CREATE TABLE IF NOT EXISTS `accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_number` VARCHAR(100) NOT NULL UNIQUE,
    `company_name` VARCHAR(255) NOT NULL,
    `type` ENUM('vendor', 'dealer') NOT NULL,
    `status` VARCHAR(50) DEFAULT 'active',
    `netsuite_id` INT,
    `email` VARCHAR(255),
    `contact_phone` VARCHAR(50),
    `address` TEXT,
    `city` VARCHAR(100),
    `state` VARCHAR(50),
    `zip_code` VARCHAR(20),
    `country` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_netsuite_id` (`netsuite_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== USERS TABLE =====
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255),
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `account_id` INT,
    `type` ENUM('user', 'vendor', 'dealer') NOT NULL DEFAULT 'user',
    `role` VARCHAR(50),
    `is_active` TINYINT(1),
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
    INDEX `idx_email` (`email`),
    INDEX `idx_type` (`type`),
    INDEX `idx_role` (`role`),
    INDEX `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== OTP TABLE =====
CREATE TABLE IF NOT EXISTS `otp_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `code` VARCHAR(10) NOT NULL,
    `user_type` ENUM('admin','user', 'vendor', 'dealer') NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `is_used` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PURCHASE ORDERS TABLE =====
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_number` VARCHAR(100) NOT NULL UNIQUE,
    `vendor_id` INT NOT NULL,
    `vendor_name` VARCHAR(255),
    `buyer_id` INT,
    `buyer_name` VARCHAR(255),
    `po_date` DATE,
    `expected_delivery_date` DATE,
    `actual_delivery_date` DATE,
    `status` VARCHAR(50),
    `amount_total` DECIMAL(12, 2),
    `currency` VARCHAR(10) DEFAULT 'USD',
    `notes` TEXT,
    `location` VARCHAR(255),
    `vessel_name` VARCHAR(255),
    `vessel_identifier` VARCHAR(100),
    `expected_factory_date` DATE,
    `rejection_reason` LONGTEXT,
    `rejected_at` TIMESTAMP NULL,
    `netsuite_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`vendor_id`) REFERENCES `accounts`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`buyer_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_po_number` (`po_number`),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_netsuite_id` (`netsuite_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PO ITEMS TABLE =====
CREATE TABLE IF NOT EXISTS `po_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `item_number` INT,
    `item_id` VARCHAR(100),
    `item_name` VARCHAR(255),
    `quantity` DECIMAL(10, 2),
    `unit_price` DECIMAL(10, 2),
    `line_total` DECIMAL(12, 2),
    `status` VARCHAR(50),
    `received_quantity` DECIMAL(10, 2) DEFAULT 0,
    `netsuite_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    INDEX `idx_po_id` (`po_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PO DOCUMENTS TABLE =====
CREATE TABLE IF NOT EXISTS `po_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(100),
    `document_type` VARCHAR(50),
    `file_size` INT,
    `uploaded_by` INT,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_po_id` (`po_id`),
    INDEX `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== ITEMS TABLE =====
CREATE TABLE IF NOT EXISTS `items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` VARCHAR(100) NOT NULL UNIQUE,
    `item_name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `category` VARCHAR(100),
    `unit_of_measure` VARCHAR(20),
    `quantity_on_hand` DECIMAL(10, 2) DEFAULT 0,
    `reorder_level` DECIMAL(10, 2),
    `price` DECIMAL(10, 2),
    `status` VARCHAR(50) DEFAULT 'active',
    `netsuite_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_item_id` (`item_id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_status` (`status`),
    INDEX `idx_netsuite_id` (`netsuite_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== EMAIL TEMPLATES TABLE =====
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `subject` VARCHAR(255) NOT NULL,
    `body` LONGTEXT NOT NULL,
    `variables` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== ACTIVITY LOG TABLE =====
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `user_name` VARCHAR(255),
    `user_type` VARCHAR(50),
    `action` VARCHAR(100),
    `entity_type` VARCHAR(50),
    `entity_id` INT,
    `old_values` JSON,
    `new_values` JSON,
    `ip_address` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== SYNC LOG TABLE =====
CREATE TABLE IF NOT EXISTS `sync_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sync_type` ENUM('accounts', 'users', 'purchase_orders', 'items') NOT NULL,
    `last_sync` TIMESTAMP,
    `status` VARCHAR(50),
    `started_by` VARCHAR(255),
    `records_processed` INT DEFAULT 0,
    `records_created` INT DEFAULT 0,
    `records_updated` INT DEFAULT 0,
    `error_message` LONGTEXT,
    `synced_count` INT DEFAULT 0,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_sync_type` (`sync_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== INVOICES TABLE =====
CREATE TABLE IF NOT EXISTS `invoices` (
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
  `department_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL,
  `netsuite_bill_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postingperiod` int NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== INVOICE LINE ITEMS TABLE =====
CREATE TABLE IF NOT EXISTS `invoice_line_items` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== INVOICE NOTES TABLE =====
CREATE TABLE IF NOT EXISTS `invoice_notes` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== INVOICE ATTACHMENTS TABLE =====
CREATE TABLE IF NOT EXISTS `invoice_attachments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PAYMENTS TABLE =====
CREATE TABLE IF NOT EXISTS `payments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== SYSTEM SETTINGS TABLE =====
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` LONGTEXT,
    `setting_type` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
