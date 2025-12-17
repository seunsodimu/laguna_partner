CREATE DATABASE IF NOT EXISTS laguna_partner;
USE laguna_partner;

-- ===== ACCOUNTS TABLE (Vendors/Dealers) =====
CREATE TABLE IF NOT EXISTS `accounts` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('vendor','dealer') COLLATE utf8mb4_unicode_ci NOT NULL,
    `company_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `category` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `email` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `phone` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `address` TEXT COLLATE utf8mb4_unicode_ci,
    `is_active` TINYINT(1) DEFAULT '1',
    `netsuite_data` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`),
    INDEX `idx_email` (`email`),
    INDEX `idx_company_name` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== CONVERSATIONS TABLE =====
CREATE TABLE IF NOT EXISTS `conversations` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `conversation_type` ENUM('vendor_to_accounting','vendor_to_buyer') COLLATE utf8mb4_unicode_ci NOT NULL,
    `vendor_id` INT NOT NULL,
    `accounting_user_id` INT DEFAULT NULL,
    `buyer_user_id` INT DEFAULT NULL,
    `subject` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `status` ENUM('active','closed','archived') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
    `last_message_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `accounting_user_id` (`accounting_user_id`),
    INDEX `buyer_user_id` (`buyer_user_id`),
    INDEX `idx_type` (`conversation_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_last_message_at` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== EMAIL TEMPLATES TABLE =====
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `subject` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `body` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
    `variables` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== INVOICES TABLE =====
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `vendor_id` INT NOT NULL,
    `vendor_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `invoice_type` ENUM('down_payment','regular') COLLATE utf8mb4_unicode_ci DEFAULT 'regular',
    `po_id` INT DEFAULT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE DEFAULT NULL,
    `amount_total` DECIMAL(12,2) NOT NULL,
    `currency` VARCHAR(10) COLLATE utf8mb4_unicode_ci DEFAULT 'USD',
    `status` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
    `payment_terms` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `estimated_payment_date` DATE DEFAULT NULL,
    `description` TEXT COLLATE utf8mb4_unicode_ci,
    `notes` LONGTEXT COLLATE utf8mb4_unicode_ci,
    `submitted_by_user_id` INT DEFAULT NULL,
    `submitted_at` TIMESTAMP NULL DEFAULT NULL,
    `reviewed_by_user_id` INT DEFAULT NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `approved_by_user_id` INT DEFAULT NULL,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `rejected_reason` LONGTEXT COLLATE utf8mb4_unicode_ci,
    `netsuite_id` INT DEFAULT NULL,
    `department_id` INT DEFAULT NULL,
    `location_id` INT DEFAULT NULL,
    `netsuite_bill_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `postingperiod` INT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `invoice_number` (`invoice_number`),
    INDEX `idx_invoice_number` (`invoice_number`),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_invoice_date` (`invoice_date`),
    INDEX `idx_netsuite_id` (`netsuite_id`),
    INDEX `submitted_by_user_id` (`submitted_by_user_id`),
    INDEX `reviewed_by_user_id` (`reviewed_by_user_id`),
    INDEX `approved_by_user_id` (`approved_by_user_id`),
    INDEX `po_id` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== INVOICE ATTACHMENTS TABLE =====
CREATE TABLE IF NOT EXISTS `invoice_attachments` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `file_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_path` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_size` INT DEFAULT NULL,
    `file_type` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `attachment_type` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_invoice_id` (`invoice_id`),
    INDEX `user_id` (`user_id`),
    INDEX `idx_attachment_type` (`attachment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== INVOICE LINE ITEMS TABLE =====
CREATE TABLE IF NOT EXISTS `invoice_line_items` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `line_number` INT DEFAULT NULL,
    `description` TEXT COLLATE utf8mb4_unicode_ci,
    `quantity` DECIMAL(10,2) DEFAULT NULL,
    `unit_price` DECIMAL(10,2) DEFAULT NULL,
    `amount` DECIMAL(12,2) DEFAULT NULL,
    `reference` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `department_id` INT DEFAULT NULL,
    `location_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== INVOICE NOTES TABLE =====
CREATE TABLE IF NOT EXISTS `invoice_notes` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `user_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_type` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `note_text` LONGTEXT COLLATE utf8mb4_unicode_ci,
    `is_internal` TINYINT(1) DEFAULT '0',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_invoice_id` (`invoice_id`),
    INDEX `user_id` (`user_id`),
    INDEX `idx_is_internal` (`is_internal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== ITEMS TABLE =====
CREATE TABLE IF NOT EXISTS `items` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `item_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` TEXT COLLATE utf8mb4_unicode_ci,
    `quantity` INT DEFAULT '0',
    `price` DECIMAL(10,2) DEFAULT NULL,
    `category` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT '1',
    `netsuite_data` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_item_id` (`item_id`),
    INDEX `idx_name` (`name`),
    INDEX `idx_quantity` (`quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== ITEM NOTIFICATIONS TABLE =====
CREATE TABLE IF NOT EXISTS `item_notifications` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `notification_type` ENUM('in_stock','out_of_stock','low_stock') COLLATE utf8mb4_unicode_ci NOT NULL,
    `threshold` INT DEFAULT '10',
    `is_active` TINYINT(1) DEFAULT '1',
    `last_notified_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_item_notification` (`user_id`,`item_id`,`notification_type`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_item_id` (`item_id`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== MESSAGES TABLE =====
CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `sender_user_id` INT DEFAULT NULL,
    `sender_type` ENUM('vendor','accounting','buyer') COLLATE utf8mb4_unicode_ci NOT NULL,
    `message_text` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
    `is_read` TINYINT(1) DEFAULT '0',
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_conversation_id` (`conversation_id`),
    INDEX `idx_sender_user_id` (`sender_user_id`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== MESSAGE ATTACHMENTS TABLE =====
CREATE TABLE IF NOT EXISTS `message_attachments` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `message_id` INT NOT NULL,
    `file_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_path` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_size` INT DEFAULT NULL,
    `file_type` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `uploaded_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== MESSAGE PARTICIPANTS TABLE =====
CREATE TABLE IF NOT EXISTS `message_participants` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `participant_type` ENUM('vendor','accounting','buyer') COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_read_message_id` INT DEFAULT NULL,
    `is_muted` TINYINT(1) DEFAULT '0',
    `joined_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_conversation_user` (`conversation_id`,`user_id`),
    INDEX `last_read_message_id` (`last_read_message_id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== OTP CODES TABLE =====
CREATE TABLE IF NOT EXISTS `otp_codes` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `user_type` ENUM('admin','buyer','vendor','dealer','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `code` VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `is_used` TINYINT(1) DEFAULT '0',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_code` (`email`,`code`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PAYMENTS TABLE =====
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `payment_number` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `invoice_id` INT NOT NULL,
    `vendor_id` INT NOT NULL,
    `amount_paid` DECIMAL(12,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_method` ENUM('ach','wire','virtual_card','check') COLLATE utf8mb4_unicode_ci NOT NULL,
    `status` ENUM('pending','processing','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
    `expected_arrival_date` DATE DEFAULT NULL,
    `reference_number` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `notes` TEXT COLLATE utf8mb4_unicode_ci,
    `created_by_user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `payment_number` (`payment_number`),
    INDEX `idx_payment_number` (`payment_number`),
    INDEX `idx_invoice_id` (`invoice_id`),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_payment_date` (`payment_date`),
    INDEX `idx_status` (`status`),
    INDEX `idx_payment_method` (`payment_method`),
    INDEX `created_by_user_id` (`created_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PAYMENT METHOD PREFERENCES TABLE =====
CREATE TABLE IF NOT EXISTS `payment_method_preferences` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT NOT NULL,
    `payment_method` ENUM('ach','wire','virtual_card','check') COLLATE utf8mb4_unicode_ci NOT NULL,
    `is_preferred` TINYINT(1) DEFAULT '0',
    `is_active` TINYINT(1) DEFAULT '1',
    `routing_number` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `account_number` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `account_holder_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `bank_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `wire_instructions` TEXT COLLATE utf8mb4_unicode_ci,
    `notes` TEXT COLLATE utf8mb4_unicode_ci,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_vendor_method` (`vendor_id`,`payment_method`),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_is_preferred` (`is_preferred`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PAYMENT RECEIPTS TABLE =====
CREATE TABLE IF NOT EXISTS `payment_receipts` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `payment_id` INT NOT NULL,
    `file_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_path` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_size` INT DEFAULT NULL,
    `file_type` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `receipt_type` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `generated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payment_id` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PO COMMENTS TABLE =====
CREATE TABLE IF NOT EXISTS `po_comments` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `user_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `user_type` ENUM('admin','buyer','vendor','dealer') COLLATE utf8mb4_unicode_ci NOT NULL,
    `comment` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
    `is_internal` TINYINT(1) DEFAULT '0',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_po_id` (`po_id`),
    INDEX `user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PO DOCUMENTS TABLE =====
CREATE TABLE IF NOT EXISTS `po_documents` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `file_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_path` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_size` INT DEFAULT NULL,
    `file_type` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `document_type` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `comment` TEXT COLLATE utf8mb4_unicode_ci,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_po_id` (`po_id`),
    INDEX `user_id` (`user_id`),
    INDEX `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PO ITEMS TABLE =====
CREATE TABLE IF NOT EXISTS `po_items` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `po_id` INT NOT NULL,
    `line_number` INT NOT NULL,
    `item_id` INT DEFAULT NULL,
    `item_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` TEXT COLLATE utf8mb4_unicode_ci,
    `quantity` DECIMAL(10,2) NOT NULL,
    `vendor_quantity` DECIMAL(10,2) DEFAULT NULL,
    `rate` DECIMAL(10,2) DEFAULT NULL,
    `amount` DECIMAL(12,2) DEFAULT NULL,
    `netsuite_data` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_po_id` (`po_id`),
    INDEX `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== PURCHASE ORDERS TABLE =====
CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tran_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `vendor_id` INT NOT NULL,
    `vendor_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `buyer_id` INT DEFAULT NULL,
    `status` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
    `status_text` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `total_amount` DECIMAL(12,2) NOT NULL,
    `currency` VARCHAR(10) COLLATE utf8mb4_unicode_ci DEFAULT 'USD',
    `created_date` DATE DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `port_date` DATE DEFAULT NULL,
    `estimated_delivery_date` DATE DEFAULT NULL,
    `ship_date` DATE DEFAULT NULL,
    `location` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `vessel_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `vessel_identifier` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `expected_factory_date` DATE DEFAULT NULL,
    `rejection_reason` LONGTEXT COLLATE utf8mb4_unicode_ci,
    `rejected_at` TIMESTAMP NULL DEFAULT NULL,
    `department` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `has_vendor_updates` TINYINT(1) DEFAULT '0',
    `is_synced_to_netsuite` TINYINT(1) DEFAULT '1',
    `netsuite_data` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tran_id` (`tran_id`),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_buyer_id` (`buyer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_date` (`created_date`),
    INDEX `idx_has_vendor_updates` (`has_vendor_updates`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== SYNC LOGS TABLE =====
CREATE TABLE IF NOT EXISTS `sync_logs` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `sync_type` ENUM('accounts','users','purchase_orders','items') COLLATE utf8mb4_unicode_ci NOT NULL,
    `status` ENUM('running','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
    `started_by` INT DEFAULT NULL,
    `records_processed` INT DEFAULT '0',
    `records_created` INT DEFAULT '0',
    `records_updated` INT DEFAULT '0',
    `records_failed` INT DEFAULT '0',
    `error_message` TEXT COLLATE utf8mb4_unicode_ci,
    `started_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_sync_type` (`sync_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_started_at` (`started_at`),
    INDEX `started_by` (`started_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== TEAMS WEBHOOK CONFIG TABLE =====
CREATE TABLE IF NOT EXISTS `teams_webhook_config` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `notification_type` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `webhook_url` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
    `channel_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `channel_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT '1',
    `description` TEXT COLLATE utf8mb4_unicode_ci,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by_user_id` INT DEFAULT NULL,
    `updated_by_user_id` INT DEFAULT NULL,
    UNIQUE KEY `notification_type` (`notification_type`),
    INDEX `idx_notification_type` (`notification_type`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `created_by_user_id` (`created_by_user_id`),
    INDEX `updated_by_user_id` (`updated_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== TERMS TABLE =====
CREATE TABLE IF NOT EXISTS `terms` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `term` VARCHAR(255) NOT NULL,
    `invoice_due_days` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ===== USERS TABLE =====
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `type` ENUM('admin','buyer','vendor','dealer','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `role` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `first_name` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `last_name` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT '1',
    `netsuite_id` INT DEFAULT NULL,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `email` (`email`),
    INDEX `idx_email` (`email`),
    INDEX `idx_type` (`type`),
    INDEX `idx_netsuite_id` (`netsuite_id`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== USER ACCOUNTS TABLE =====
CREATE TABLE IF NOT EXISTS `user_accounts` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `account_id` INT NOT NULL,
    `is_primary` TINYINT(1) DEFAULT '0',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_account` (`user_id`,`account_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== USER LOGS TABLE =====
CREATE TABLE IF NOT EXISTS `user_logs` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `entity_type` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `entity_id` INT DEFAULT NULL,
    `details` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== VENDOR DOCUMENTS TABLE =====
CREATE TABLE IF NOT EXISTS `vendor_documents` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT NOT NULL,
    `document_type` ENUM('w9','w8','insurance_certificate','tax_exemption','banking_verification','other') COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_path` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
    `file_size` INT DEFAULT NULL,
    `file_type` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `expiration_date` DATE DEFAULT NULL,
    `is_verified` TINYINT(1) DEFAULT '0',
    `verified_by_user_id` INT DEFAULT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `notes` TEXT COLLATE utf8mb4_unicode_ci,
    `uploaded_by_user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_document_type` (`document_type`),
    INDEX `idx_expiration_date` (`expiration_date`),
    INDEX `verified_by_user_id` (`verified_by_user_id`),
    INDEX `uploaded_by_user_id` (`uploaded_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== VENDOR PROFILES TABLE =====
CREATE TABLE IF NOT EXISTS `vendor_profiles` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT NOT NULL,
    `tax_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `w9_file_path` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `w9_uploaded_at` TIMESTAMP NULL DEFAULT NULL,
    `w9_expires_at` DATE DEFAULT NULL,
    `primary_contact_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `primary_contact_email` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `primary_contact_phone` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `secondary_contact_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `secondary_contact_email` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `secondary_contact_phone` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `billing_address_1` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `billing_address_2` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `billing_city` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `billing_state` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `billing_zip` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `billing_country` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `shipping_address_1` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `shipping_address_2` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `shipping_city` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `shipping_state` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `shipping_zip` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `shipping_country` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `preferred_communication` ENUM('email','phone','both') COLLATE utf8mb4_unicode_ci DEFAULT 'email',
    `notes` TEXT COLLATE utf8mb4_unicode_ci,
    `term` INT NOT NULL DEFAULT '1',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `vendor_id` (`vendor_id`),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `vendor_profiles_ibfk_2` (`term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== ADD FOREIGN KEY CONSTRAINTS =====
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`accounting_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_4` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_5` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoice_attachments`
  ADD CONSTRAINT `invoice_attachments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_attachments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoice_line_items`
  ADD CONSTRAINT `invoice_line_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

ALTER TABLE `invoice_notes`
  ADD CONSTRAINT `invoice_notes_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_notes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `item_notifications`
  ADD CONSTRAINT `item_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_notifications_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `message_attachments`
  ADD CONSTRAINT `message_attachments_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

ALTER TABLE `message_participants`
  ADD CONSTRAINT `message_participants_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_participants_ibfk_3` FOREIGN KEY (`last_read_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL;

ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `payment_method_preferences`
  ADD CONSTRAINT `payment_method_preferences_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

ALTER TABLE `payment_receipts`
  ADD CONSTRAINT `payment_receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

ALTER TABLE `po_comments`
  ADD CONSTRAINT `po_comments_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `po_documents`
  ADD CONSTRAINT `po_documents_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_documents_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `po_items`
  ADD CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

ALTER TABLE `sync_logs`
  ADD CONSTRAINT `sync_logs_ibfk_1` FOREIGN KEY (`started_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `teams_webhook_config`
  ADD CONSTRAINT `teams_webhook_config_ibfk_1` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teams_webhook_config_ibfk_2` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `user_accounts`
  ADD CONSTRAINT `user_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_accounts_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_logs`
  ADD CONSTRAINT `user_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `vendor_documents`
  ADD CONSTRAINT `vendor_documents_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_documents_ibfk_2` FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vendor_documents_ibfk_3` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `vendor_profiles`
  ADD CONSTRAINT `vendor_profiles_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vendor_profiles_ibfk_2` FOREIGN KEY (`term`) REFERENCES `terms` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT;

-- ===== INSERT DATA =====
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
(12, 'po_rejection', 'Purchase Order {{po_number}} Rejected', '<html>\r\n<head>\r\n  <style>\r\n    body { font-family: Arial, sans-serif; color: #333; }\r\n    .container { max-width: 600px; margin: 0 auto; padding: 20px; }\r\n    .header { background-color: #d9534f; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }\r\n    .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; }\r\n    .po-details { background-color: white; padding: 15px; border-left: 4px solid #d9534f; margin: 15px 0; }\r\n    .footer { background-color: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 5px 5px; }\r\n    .button { display: inline-block; background-color: #d9534f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; margin: 10px 0; }\r\n  </style>\r\n</head>\r\n<body>\r\n  <div class=\"container\">\r\n    <div class=\"header\">\r\n      <h2>Purchase Order Rejected</h2>\r\n    </div>\r\n    <div class=\"content\">\r\n      <p>Dear Buyer,</p>\r\n      <p><strong>{{vendor_name}}</strong> has rejected the following purchase order:</p>\r\n      <div class=\"po-details\">\r\n        <p><strong>PO Number:</strong> {{po_number}}</p>\r\n        <p><strong>Total Amount:</strong> {{total_amount}}</p>\r\n        <p><strong>Rejected Date:</strong> {{rejected_date}}</p>\r\n      </div>\r\n      <h3>Rejection Reason:</h3>\r\n      <p>{{rejection_reason}}</p>\r\n      <p>Please contact the vendor for more information or take appropriate action.</p>\r\n      <p>\r\n        <a href=\"{{portal_link}}\" class=\"button\">View PO Details</a>\r\n      </p>\r\n    </div>\r\n    <div class=\"footer\">\r\n      <p>Laguna Partners Portal</p>\r\n      <p>This is an automated notification. Please do not reply to this email.</p>\r\n    </div>\r\n  </div>\r\n</body>\r\n</html>', '{\"po_number\": \"string\", \"portal_link\": \"url\", \"vendor_name\": \"string\", \"total_amount\": \"string\", \"rejection_reason\": \"text\", \"rejected_date\": \"date\"}', '2025-11-06 16:02:24', '2025-11-06 16:24:34');

INSERT INTO `teams_webhook_config` (`id`, `notification_type`, `webhook_url`, `channel_id`, `channel_name`, `is_active`, `description`, `created_at`, `updated_at`, `created_by_user_id`, `updated_by_user_id`) VALUES
(1, 'po_vendor_update', 'https://lagunatoolsusa.webhook.office.com/webhookb2/55e610bd-fde0-4a3d-9b95-1964129a0fc0@ea621bf3-c6d4-48bf-b04a-0c95687f094b/IncomingWebhook/f53eb6e9141c4b4d94ff25d70fdf6fe8/bc6a053b-6899-428c-a8ce-7a4caa8cb2cc/V2u0Am5bl91GbR0UeWwJdlQgjopDc-9DB4AwLtpJnvVI41', NULL, 'Purchase Orders', 1, 'Notification sent when a vendor updates a Purchase Order', '2025-11-19 22:34:41', '2025-11-19 23:17:52', NULL, 1),
(2, 'invoice_submitted', 'https://lagunatoolsusa.webhook.office.com/webhookb2/55e610bd-fde0-4a3d-9b95-1964129a0fc0@ea621bf3-c6d4-48bf-b04a-0c95687f094b/IncomingWebhook/006f0d02fcf24b4eabde2c85b7f75056/bc6a053b-6899-428c-a8ce-7a4caa8cb2cc/V24NZs7Dmy8u1Kq03fSmU6K9klHjPzffzWNDtz5JcqtA81', NULL, 'Invoices', 1, 'Notification sent when a vendor submits an invoice', '2025-11-19 22:34:41', '2025-11-19 23:05:22', NULL, 1),
(3, 'message_buyer', 'https://lagunatoolsusa.webhook.office.com/webhookb2/55e610bd-fde0-4a3d-9b95-1964129a0fc0@ea621bf3-c6d4-48bf-b04a-0c95687f094b/IncomingWebhook/f53eb6e9141c4b4d94ff25d70fdf6fe8/bc6a053b-6899-428c-a8ce-7a4caa8cb2cc/V2u0Am5bl91GbR0UeWwJdlQgjopDc-9DB4AwLtpJnvVI41', NULL, 'Purchase Orders', 1, 'Notification sent when a vendor messages the buyer team', '2025-11-19 22:34:41', '2025-11-20 21:16:28', NULL, 1),
(4, 'message_accounting', 'https://lagunatoolsusa.webhook.office.com/webhookb2/55e610bd-fde0-4a3d-9b95-1964129a0fc0@ea621bf3-c6d4-48bf-b04a-0c95687f094b/IncomingWebhook/006f0d02fcf24b4eabde2c85b7f75056/bc6a053b-6899-428c-a8ce-7a4caa8cb2cc/V24NZs7Dmy8u1Kq03fSmU6K9klHjPzffzWNDtz5JcqtA81', NULL, 'Invoices', 1, 'Notification sent when a vendor messages the accounting team', '2025-11-19 22:34:41', '2025-11-20 21:16:51', NULL, 1);

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

INSERT INTO `users` (`id`,`email`, `type`, `role`, `first_name`, `last_name`, `is_active`, `netsuite_id`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'web_dev@lagunatools.com', 'user', 'admin', 'Admin', 'User', 1, NULL, '2025-11-21 15:03:41', '2025-11-04 20:05:05', '2025-11-21 15:03:41');

COMMIT;
