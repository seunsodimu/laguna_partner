-- Laguna Partners Portal Database Schema
-- Created: 2025

-- Drop tables if they exist (in reverse order of dependencies)
DROP TABLE IF EXISTS `item_notifications`;
DROP TABLE IF EXISTS `po_documents`;
DROP TABLE IF EXISTS `po_comments`;
DROP TABLE IF EXISTS `po_items`;
DROP TABLE IF EXISTS `purchase_orders`;
DROP TABLE IF EXISTS `items`;
DROP TABLE IF EXISTS `user_logs`;
DROP TABLE IF EXISTS `sync_logs`;
DROP TABLE IF EXISTS `email_templates`;
DROP TABLE IF EXISTS `otp_codes`;
DROP TABLE IF EXISTS `user_accounts`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `accounts`;

-- Accounts table (Vendors/Dealers from NetSuite)
CREATE TABLE `accounts` (
    `id` INT PRIMARY KEY,  -- Same as NetSuite ID
    `type` ENUM('vendor', 'dealer') NOT NULL,
    `company_name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100),
    `email` VARCHAR(255),
    `phone` VARCHAR(50),
    `address` TEXT,
    `is_active` BOOLEAN DEFAULT TRUE,
    `netsuite_data` JSON,  -- Store full NetSuite record
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`),
    INDEX `idx_email` (`email`),
    INDEX `idx_company_name` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `type` ENUM('admin', 'buyer', 'vendor', 'dealer') NOT NULL,
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `is_active` BOOLEAN DEFAULT TRUE,
    `netsuite_id` INT,  -- Employee ID for buyers/admins
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_type` (`type`),
    INDEX `idx_netsuite_id` (`netsuite_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User-Account relationship (many-to-many)
CREATE TABLE `user_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `is_primary` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_account` (`user_id`, `account_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OTP codes for authentication
CREATE TABLE `otp_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `user_type` ENUM('admin', 'buyer', 'vendor', 'dealer') NOT NULL,
    `code` VARCHAR(10) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `is_used` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_code` (`email`, `code`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email templates
CREATE TABLE `email_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `variables` JSON,  -- Available template variables
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync logs
CREATE TABLE `sync_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sync_type` ENUM('accounts', 'users', 'purchase_orders', 'items') NOT NULL,
    `status` ENUM('running', 'completed', 'failed') NOT NULL,
    `started_by` INT,  -- User ID who initiated (NULL for scheduled)
    `records_processed` INT DEFAULT 0,
    `records_created` INT DEFAULT 0,
    `records_updated` INT DEFAULT 0,
    `records_failed` INT DEFAULT 0,
    `error_message` TEXT,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    FOREIGN KEY (`started_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_sync_type` (`sync_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User activity logs
CREATE TABLE `user_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50),  -- e.g., 'purchase_order', 'item'
    `entity_id` INT,
    `details` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items table (for dealer portal)
CREATE TABLE `items` (
    `id` INT PRIMARY KEY,  -- Same as NetSuite ID
    `item_id` VARCHAR(100) NOT NULL,  -- SKU
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `quantity` INT DEFAULT 0,
    `price` DECIMAL(10, 2),
    `category` VARCHAR(100),
    `is_active` BOOLEAN DEFAULT TRUE,
    `netsuite_data` JSON,  -- Store full NetSuite record
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_item_id` (`item_id`),
    INDEX `idx_name` (`name`),
    INDEX `idx_quantity` (`quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Orders table
CREATE TABLE `purchase_orders` (
    `id` INT PRIMARY KEY,  -- Same as NetSuite ID
    `tran_id` VARCHAR(100) NOT NULL,  -- PO Number
    `vendor_id` INT NOT NULL,
    `vendor_name` VARCHAR(255) NOT NULL,
    `buyer_id` INT,  -- Employee ID from NetSuite
    `status` VARCHAR(50) NOT NULL,  -- B, E, F, H
    `status_text` VARCHAR(100),  -- Human readable status
    `total_amount` DECIMAL(12, 2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'USD',
    `created_date` DATE,
    `due_date` DATE,
    `port_date` DATE,
    `estimated_delivery_date` DATE,
    `ship_date` DATE,
    `location` VARCHAR(255),
    `department` VARCHAR(255),
    `has_vendor_updates` BOOLEAN DEFAULT FALSE,  -- Flag for outstanding vendor updates
    `is_synced_to_netsuite` BOOLEAN DEFAULT TRUE,  -- FALSE when updated in portal
    `netsuite_data` JSON,  -- Store full NetSuite record
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`vendor_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
    INDEX `idx_tran_id` (`tran_id`),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_buyer_id` (`buyer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_date` (`created_date`),
    INDEX `idx_has_vendor_updates` (`has_vendor_updates`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Order Items
CREATE TABLE `po_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `line_number` INT NOT NULL,
    `item_id` INT,
    `item_name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `quantity` DECIMAL(10, 2) NOT NULL,
    `vendor_quantity` DECIMAL(10, 2),  -- Quantity confirmed by vendor
    `rate` DECIMAL(10, 2),
    `amount` DECIMAL(12, 2),
    `netsuite_data` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    INDEX `idx_po_id` (`po_id`),
    INDEX `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Order Comments
CREATE TABLE `po_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `user_id` INT,
    `user_name` VARCHAR(255) NOT NULL,
    `user_type` ENUM('admin', 'buyer', 'vendor', 'dealer') NOT NULL,
    `comment` TEXT NOT NULL,
    `is_internal` BOOLEAN DEFAULT FALSE,  -- Internal notes not visible to vendors
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_po_id` (`po_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Order Documents
CREATE TABLE `po_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `user_id` INT,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` INT,
    `file_type` VARCHAR(100),
    `comment` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_po_id` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Item Notifications (for dealers)
CREATE TABLE `item_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `notification_type` ENUM('in_stock', 'out_of_stock', 'low_stock') NOT NULL,
    `threshold` INT DEFAULT 10,  -- For low_stock notifications
    `is_active` BOOLEAN DEFAULT TRUE,
    `last_notified_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `items`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_item_notification` (`user_id`, `item_id`, `notification_type`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_item_id` (`item_id`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
INSERT INTO `users` (`email`, `type`, `first_name`, `last_name`, `is_active`) 
VALUES ('admin@lagunatools.com', 'admin', 'Admin', 'User', TRUE);

-- Insert default email templates
INSERT INTO `email_templates` (`name`, `subject`, `body`, `variables`) VALUES
('otp_login', 'Your Login Code for Laguna Partners Portal', 
'Hello,\n\nYour one-time password (OTP) for accessing the Laguna Partners Portal is:\n\n{{otp_code}}\n\nThis code will expire in 15 minutes.\n\nIf you did not request this code, please ignore this email.\n\nBest regards,\nLaguna Tools Team',
'["otp_code", "user_email"]'),

('vendor_po_update', 'Purchase Order Updated - {{po_number}}',
'Hello,\n\nPurchase Order {{po_number}} has been updated by the vendor.\n\n**Changes Made:**\n{{changes}}\n\n**Purchase Order Details:**\n- PO Number: {{po_number}}\n- Vendor: {{vendor_name}}\n- Total Amount: {{total_amount}}\n- Status: {{status}}\n\n**Updated Fields:**\n{{updated_fields}}\n\nPlease review and approve these changes in the portal.\n\nView PO: {{portal_link}}\n\nBest regards,\nLaguna Tools Team',
'["po_number", "vendor_name", "total_amount", "status", "changes", "updated_fields", "portal_link"]'),

('buyer_approve_request', 'Purchase Order Changes Approved - {{po_number}}',
'Hello,\n\nThe changes you requested for Purchase Order {{po_number}} have been approved by the buyer.\n\n**Purchase Order Details:**\n- PO Number: {{po_number}}\n- Total Amount: {{total_amount}}\n- Status: {{status}}\n\n**Approved Changes:**\n{{approved_changes}}\n\nThese changes have been synced to NetSuite.\n\nView PO: {{portal_link}}\n\nBest regards,\nLaguna Tools Team',
'["po_number", "total_amount", "status", "approved_changes", "portal_link"]'),

('item_in_stock', 'Item Now Available - {{item_name}}',
'Hello,\n\nGood news! The item you requested to be notified about is now in stock.\n\n**Item Details:**\n- Name: {{item_name}}\n- SKU: {{item_sku}}\n- Quantity Available: {{quantity}}\n\nView Item: {{portal_link}}\n\nBest regards,\nLaguna Tools Team',
'["item_name", "item_sku", "quantity", "portal_link"]'),

('item_out_of_stock', 'Item Out of Stock - {{item_name}}',
'Hello,\n\nThe item you requested to be notified about is now out of stock.\n\n**Item Details:**\n- Name: {{item_name}}\n- SKU: {{item_sku}}\n\nWe will notify you when it becomes available again.\n\nView Item: {{portal_link}}\n\nBest regards,\nLaguna Tools Team',
'["item_name", "item_sku", "portal_link"]'),

('item_low_stock', 'Item Low Stock Alert - {{item_name}}',
'Hello,\n\nThe item you requested to be notified about is running low on stock.\n\n**Item Details:**\n- Name: {{item_name}}\n- SKU: {{item_sku}}\n- Quantity Available: {{quantity}}\n\nView Item: {{portal_link}}\n\nBest regards,\nLaguna Tools Team',
'["item_name", "item_sku", "quantity", "portal_link"]');