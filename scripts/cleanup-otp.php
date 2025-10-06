#!/usr/bin/env php
<?php
/**
 * Cleanup Expired OTP Codes
 * Run via cron: 0 3 * * * php /path/to/cleanup-otp.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

echo "Starting OTP cleanup...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('-', 50) . "\n";

try {
    $db = Database::getInstance();
    
    // Delete expired OTP codes (older than 24 hours)
    $result = $db->query(
        "DELETE FROM otp_codes WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );
    
    $deleted = $result->rowCount();
    
    echo "✓ Cleanup completed successfully!\n";
    echo "  Deleted {$deleted} expired OTP codes\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}