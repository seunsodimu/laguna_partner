<?php
/**
 * Main Configuration File
 * 
 * This file contains the main configuration settings for the Laguna Partners Portal.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Load credentials
require_once __DIR__ . '/credentials.php';

return [
    // Application Settings
    'app' => [
        'name' => 'Laguna Partners Portal',
        'version' => '1.0.0',
        'timezone' => 'America/New_York',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],

    // Logging Configuration
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'file' => __DIR__ . '/../logs/app-' . date('Y-m-d') . '.log',
        'max_files' => 30,
    ],

    // Database Configuration
    'database' => [
        'enabled' => true,
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_NAME'] ?? 'laguna_partner',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
        'charset' => 'utf8mb4',
    ],

    // File Upload Settings
    'upload' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'png', 'gif'],
        'upload_path' => __DIR__ . '/../uploads/',
    ],

    // Email Notification Settings
    'notifications' => [
        'enabled' => true,
        'from_email' => $_ENV['NOTIFICATION_FROM_EMAIL'] ?? 'noreply@lagunatools.com',
        'from_name' => $_ENV['NOTIFICATION_FROM_NAME'] ?? 'Laguna Partners Portal',
        'to_emails' => explode(',', $_ENV['NOTIFICATION_TO_EMAILS'] ?? 'seun_sodimu@lagunatools.com'),
        'subject_prefix' => '[Laguna Partners] ',
    ],

    // Purchase Order Settings
    'purchase_orders' => [
        'editable_statuses' => ['B', 'E'], // Statuses where vendors can edit dates (B = Pending Received, E = Partially Received)
        'sync_statuses' => ['B', 'E', 'F', 'H'], // Statuses to sync from NetSuite
        'require_buyer_approval' => true, // Require buyer approval before syncing vendor changes to NetSuite
        'notify_on_vendor_update' => true, // Send email to buyer when vendor updates PO
    ],

    // Item Notification Settings
    'item_notifications' => [
        'default_low_stock_threshold' => 10, // Default threshold for low stock alerts
        'check_frequency' => 6, // Hours between item sync checks
    ],

    // NetSuite Settings
    'netsuite' => [
        'default_subsidiary_id' => 1,
        'default_location_id' => 1,
        'sync_batch_size' => 100, // Number of records to process per batch
        'request_delay' => 100, // Milliseconds between API requests
    ],

    // API Rate Limiting
    'rate_limiting' => [
        'netsuite_requests_per_minute' => 10,
    ],

    // Session Settings
    'session' => [
        'lifetime' => 3600, // 1 hour
        'name' => 'LAGUNA_SESSION',
        'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    // OTP Settings
    'otp' => [
        'length' => 6,
        'expiry' => 900, // 15 minutes in seconds
        'cleanup_after' => 86400, // 24 hours in seconds
    ],
];