CREATE DATABASE IF NOT EXISTS laguna_partner;
USE laguna_partner;

-- Base Schema for Laguna Partners Portal
-- Created: 2025

-- ===== ACCOUNTS TABLE (Vendors/Dealers) =====
CREATE TABLE IF NOT EXISTS `accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL,
    `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `type` ENUM('vendor', 'dealer') NOT NULL,
    `status` VARCHAR(50) DEFAULT 'active',
    `netsuite_id` INT,
    `email` VARCHAR(255),
    `contact_phone` VARCHAR(50),
    `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `address` TEXT,
    `city` VARCHAR(100),
    `state` VARCHAR(50),
    `zip_code` VARCHAR(20),
    `country` VARCHAR(100),
    `is_active` TINYINT(1),
    `netsuite_data` json DEFAULT NULL,
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
  `netsuite_id` int DEFAULT NULL,
    `last_login` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
    INDEX `idx_email` (`email`),
    INDEX `idx_type` (`type`),
    INDEX `idx_role` (`role`),
    INDEX `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`email`, `type`, `role`, `first_name`, `last_name`, `is_active`, `netsuite_id`, `last_login`, `created_at`, `updated_at`) VALUES
('web_dev@lagunatools.com', 'user', 'admin', 'Admin', 'User', 1, NULL, '2025-11-21 15:03:41', '2025-11-04 20:05:05', '2025-11-21 15:03:41');

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

INSERT INTO `email_templates` (`id`, `name`, `subject`, `body`, `variables`, `created_at`, `updated_at`) VALUES
(1, 'otp_login', 'Your Login Code for Laguna Partners Portal', 'Hello,\n\nYour one-time password (OTP) for accessing the Laguna Partners Portal is:\n\n{{otp_code}}\n\nThis code will expire in 15 minutes.\n\nIf you did not request this code, please ignore this email.\n\nBest regards,\nLaguna Tools Team', '[\"otp_code\", \"user_email\"]', '2025-11-04 20:05:05', '2025-11-04 20:05:05'),
(2, 'vendor_po_update', 'Purchase Order Updated - {{po_number}}', 'Hello,\n\nPurchase Order {{po_number}} has been updated by the vendor.\n\n**Changes Made:**\n{{changes}}\n\n**Purchase Order Details:**\n- PO Number: {{po_number}}\n- Vendor: {{vendor_name}}\n- Total Amount: {{total_amount}}\n- Status: {{status}}\n\n**Updated Fields:**\n{{updated_fields}}\n\nPlease review and approve these changes in the portal.\n\nView PO: {{portal_link}}\n\nBest regards,\nLaguna Tools Team', '[\"po_number\", \"vendor_name\", \"total_amount\", \"status\", \"changes\", \"updated_fields\", \"portal_link\"]', '2025-11-04 20:05:05', '2025-11-04 20:05:05'),
(3, 'buyer_approve_request', 'Purchase Order Changes Approved - {{po_number}}', 'Hello,\n\nThe changes you requested for Purchase Order {{po_number}} have been approved by the buyer.\n\n**Purchase Order Details:**\n- PO Number: {{po_number}}\n- Total Amount: {{total_amount}}\n- Status: {{status}}\n\n**Approved Changes:**\n{{approved_changes}}\n\nThese changes have been synced to NetSuite.\n\nView PO: {{portal_link}}\n\nBest regards,\nLaguna Tools Team', '[\"po_number\", \"total_amount\", \"status\", \"approved_changes\", \"portal_link\"]', '2025-11-04 20:05:05', '2025-11-04 20:05:05'),
(4, 'item_in_stock', 'Item Now Available - {{item_name}}', 'Hello,\n\nGood news! The item you requested to be notified about is now in stock.\n\n**Item Details:**\n- Name: {{item_name}}\n- SKU: {{item_sku}}\n- Quantity Available: {{quantity}}\n\nView Item: {{portal_link}}\n\nBest regards,\nLaguna Tools Team', '[\"item_name\", \"item_sku\", \"quantity\", \"portal_link\"]', '2025-11-04 20:05:05', '2025-11-04 20:05:05'),
(5, 'item_out_of_stock', 'Item Out of Stock - {{item_name}}', 'Hello,\n\nThe item you requested to be notified about is now out of stock.\n\n**Item Details:**\n- Name: {{item_name}}\n- SKU: {{item_sku}}\n\nWe will notify you when it becomes available again.\n\nView Item: {{portal_link}}\n\nBest regards,\nLaguna Tools Team', '[\"item_name\", \"item_sku\", \"portal_link\"]', '2025-11-04 20:05:05', '2025-11-04 20:05:05'),
(6, 'item_low_stock', 'Item Low Stock Alert - {{item_name}}', 'Hello,\n\nThe item you requested to be notified about is running low on stock.\n\n**Item Details:**\n- Name: {{item_name}}\n- SKU: {{item_sku}}\n- Quantity Available: {{quantity}}\n\nView Item: {{portal_link}}\n\nBest regards,\nLaguna Tools Team', '[\"item_name\", \"item_sku\", \"quantity\", \"portal_link\"]', '2025-11-04 20:05:05', '2025-11-04 20:05:05'),
(7, 'invoice_submitted', 'Invoice Submitted for Review - {{invoice_number}}', 'Hello,\n\nA new invoice has been submitted by vendor {{vendor_name}} and requires your review.\n\n**Invoice Details:**\n- Invoice #: {{invoice_number}}\n- Vendor: {{vendor_name}}\n- Amount: {{amount_total}} {{currency}}\n- Invoice Date: {{invoice_date}}\n- Due Date: {{due_date}}\n- PO Reference: {{po_number}}\n\n**Status:** {{status}}\n\nPlease review the invoice and either approve or request corrections in the portal.\n\nView Invoice: {{portal_link}}\n\nBest regards,\nLaguna Tools Team', '[\"invoice_number\", \"vendor_name\", \"amount_total\", \"currency\", \"invoice_date\", \"due_date\", \"po_number\", \"status\", \"portal_link\"]', '2025-11-06 16:02:24', '2025-11-06 16:24:34'),
(8, 'invoice_approved', 'Invoice Approved - {{invoice_number}}', 'Hello,\n\nYour invoice has been approved and is now being processed for payment.\n\n**Invoice Details:**\n- Invoice #: {{invoice_number}}\n- Amount: {{amount_total}} {{currency}}\n- Due Date: {{due_date}}\n- Estimated Payment Date: {{estimated_payment_date}}\n\n**Status:** {{status}}\n\nYou can track payment status in the portal.\n\nView Invoice: {{portal_link}}\n\nBest regards,\nLaguna Tools Team', '[\"invoice_number\", \"amount_total\", \"currency\", \"due_date\", \"estimated_payment_date\", \"status\", \"portal_link\"]', '2025-11-06 16:02:24', '2025-11-06 16:24:34'),
(9, 'invoice_needs_correction', 'Invoice Needs Correction - {{invoice_number}}', 'Hello,\n\nYour invoice {{invoice_number}} requires corrections before it can be approved.\n\n**Invoice Details:**\n- Invoice #: {{invoice_number}}\n- Amount: {{amount_total}} {{currency}}\n\n**Reason for Request:**\n{{correction_reason}}\n\nPlease review the notes in the portal and resubmit the corrected invoice.\n\nView Invoice: {{portal_link}}\n\nBest regards,\nLaguna Tools Team', '[\"invoice_number\", \"amount_total\", \"currency\", \"correction_reason\", \"portal_link\"]', '2025-11-06 16:02:24', '2025-11-06 16:24:34'),
(10, 'payment_processed', 'Payment Processed - {{invoice_number}}', 'Hello,\n\nPayment for your invoice {{invoice_number}} has been processed.\n\n**Payment Details:**\n- Invoice #: {{invoice_number}}\n- Amount Paid: {{amount_paid}} {{currency}}\n- Payment Date: {{payment_date}}\n- Payment Method: {{payment_method}}\n- Expected Arrival: {{expected_arrival_date}}\n- Reference #: {{reference_number}}\n\nYou can download your payment receipt and remittance advice from the portal.\n\nView Payment: {{portal_link}}\n\nBest regards,\nLaguna Tools Team', '[\"invoice_number\", \"amount_paid\", \"currency\", \"payment_date\", \"payment_method\", \"expected_arrival_date\", \"reference_number\", \"portal_link\"]', '2025-11-06 16:02:24', '2025-11-06 16:24:34'),
(12, 'po_rejection', 'Purchase Order {{po_number}} Rejected', '<html>\r\n<head>\r\n  <style>\r\n    body { font-family: Arial, sans-serif; color: #333; }\r\n    .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n    .header { background-color: #d9534f; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }\r\n    .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }\r\n    .po-details { background-color: white; padding: 15px; border-left: 4px solid #d9534f; margin: 15px 0; }\r\n    .footer { background-color: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 5px 5px; }\r\n    .button { display: inline-block; background-color: #d9534f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; margin: 10px 0; }\r\n  </style>\r\n</head>\r\n<body>\r\n  <div class=\"container\">\r\n    <div class=\"header\">\r\n      <h2>Purchase Order Rejected</h2>\r\n    </div>\r\n    <div class=\"content\">\r\n      <p>Dear Buyer,</p>\r\n      <p><strong>{{vendor_name}}</strong> has rejected the following purchase order:</p>\r\n      <div class=\"po-details\">\r\n        <p><strong>PO Number:</strong> {{po_number}}</p>\r\n        <p><strong>Total Amount:</strong> {{total_amount}}</p>\r\n        <p><strong>Rejected Date:</strong> {{rejected_date}}</p>\r\n      </div>\r\n      <h3>Rejection Reason:</h3>\r\n      <p>{{rejection_reason}}</p>\r\n      <p>Please contact the vendor for more information or take appropriate action.</p>\r\n      <p>\r\n        <a href=\"{{portal_link}}\" class=\"button\">View PO Details</a>\r\n      </p>\r\n    </div>\r\n    <div class=\"footer\">\r\n      <p>Laguna Partners Portal</p>\r\n      <p>This is an automated notification. Please do not reply to this email.</p>\r\n    </div>\r\n  </div>\r\n</body>\r\n</html>', '{\"po_number\": \"string\", \"portal_link\": \"url\", \"vendor_name\": \"string\", \"total_amount\": \"string\", \"rejected_date\": \"string\", \"rejection_reason\": \"string\"}', '2025-11-20 16:40:15', '2025-11-20 16:40:15');


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
    `records_failed` INT DEFAULT 0,
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


-- ===== TERMS TABLE =====
CREATE TABLE IF NOT EXISTS `terms` (
  `id` int NOT NULL,
  `term` varchar(255) NOT NULL,
  `invoice_due_days` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `terms` (`id`, `term`, `invoice_due_days`) VALUES
(1, 'Net 15', 15),
(2, 'Net 30', 30),
(3, 'Net 60', 60),
(4, 'Due on receipt', 0),
(5, '1% 10 Net 30', 30),
(6, '2% 10 Net 30', 30),
(7, '2% 10 Net 60', 60),
(8, '2% 15 Net 30', 30),
(9, 'Net 10', 10),
(10, 'Net 120', 120),
(11, 'Net 150', 150),
(12, 'Net 180', 180),
(13, 'Net 20', 20),
(14, 'Net 45', 45),
(15, 'Net 75', 75),
(16, 'Net 90', 90),
(17, 'Net 25', 25),
(18, 'In Advance', -1),
(19, 'Warranty', -1),
(20, 'No Charge', -1),
(21, 'Exchange', -1),
(22, '25% Net 30', 30);
