<?php
/**
 * Email Configuration Verification Script
 * 
 * This script helps diagnose email service configuration issues.
 * Usage: php scripts/verify-email-config.php
 */

// Load environment and config
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$config = require __DIR__ . '/../config/config.php';

echo "=== Email Configuration Verification ===\n\n";

// 1. Check email provider
$provider = $_ENV['EMAIL_PROVIDER'] ?? 'brevo';
echo "1. Email Provider: $provider\n";

// 2. Check general notification settings
echo "\n2. Notification Settings:\n";
echo "   From Email: " . ($config['notifications']['from_email'] ?? 'NOT SET') . "\n";
echo "   From Name: " . ($config['notifications']['from_name'] ?? 'NOT SET') . "\n";

// 3. Check Brevo configuration
echo "\n3. Brevo Configuration:\n";
$brevoKey = $_ENV['BREVO_API_KEY'] ?? null;
echo "   API Key: " . ($brevoKey ? 'SET (' . strlen($brevoKey) . ' chars)' : 'NOT SET') . "\n";
echo "   From Email: " . ($_ENV['BREVO_FROM_EMAIL'] ?? 'NOT SET') . "\n";
echo "   From Name: " . ($_ENV['BREVO_FROM_NAME'] ?? 'NOT SET') . "\n";

// 4. Check AWS SES configuration
echo "\n4. AWS SES Configuration:\n";
$accessKey = $_ENV['AWS_ACCESS_KEY_ID'] ?? null;
$secretKey = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null;
$region = $_ENV['AWS_SES_REGION'] ?? 'NOT SET';
echo "   Access Key: " . ($accessKey ? 'SET (' . strlen($accessKey) . ' chars)' : 'NOT SET') . "\n";
echo "   Secret Key: " . ($secretKey ? 'SET (' . strlen($secretKey) . ' chars)' : 'NOT SET') . "\n";
echo "   Region: " . $region . "\n";
echo "   From Email: " . ($_ENV['SES_FROM_EMAIL'] ?? 'NOT SET') . "\n";
echo "   From Name: " . ($_ENV['SES_FROM_NAME'] ?? 'NOT SET') . "\n";

// 5. Check logs directory
echo "\n5. Logs Directory:\n";
$logsDir = __DIR__ . '/../logs';
echo "   Path: $logsDir\n";
echo "   Exists: " . (is_dir($logsDir) ? 'YES' : 'NO') . "\n";
echo "   Writable: " . (is_writable($logsDir) ? 'YES' : 'NO') . "\n";
echo "   Permissions: " . substr(sprintf('%o', fileperms($logsDir)), -4) . "\n";

// 6. Try to create a test log file
echo "\n6. Test Log File Creation:\n";
$testLogFile = $logsDir . '/email-test-' . date('Y-m-d-His') . '.log';
$testMessage = "[" . date('Y-m-d H:i:s') . "] Test log entry\n";

try {
    if (file_put_contents($testLogFile, $testMessage, FILE_APPEND | LOCK_EX)) {
        echo "   ✓ Successfully created: " . basename($testLogFile) . "\n";
        echo "   File size: " . filesize($testLogFile) . " bytes\n";
        echo "   File permissions: " . substr(sprintf('%o', fileperms($testLogFile)), -4) . "\n";
    } else {
        echo "   ✗ Failed to create log file\n";
    }
} catch (\Throwable $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

// 7. Test curl availability
echo "\n7. cURL Support:\n";
echo "   Enabled: " . (function_exists('curl_init') ? 'YES' : 'NO') . "\n";
if (function_exists('curl_version')) {
    $curlVersion = curl_version();
    echo "   Version: " . $curlVersion['version'] . "\n";
    echo "   SSL Support: " . ($curlVersion['features'] & CURL_VERSION_SSL ? 'YES' : 'NO') . "\n";
}

// 8. Test timezone
echo "\n8. Timezone Settings:\n";
echo "   PHP Default: " . ini_get('date.timezone') . "\n";
echo "   Current: " . date_default_timezone_get() . "\n";
echo "   Date: " . date('Y-m-d H:i:s') . "\n";
echo "   UTC Date: " . gmdate('Y-m-d H:i:s') . "\n";

// 9. Test JSON support
echo "\n9. JSON Support:\n";
echo "   Enabled: " . (extension_loaded('json') ? 'YES' : 'NO') . "\n";

// 10. Test hash functions (for AWS SigV4)
echo "\n10. Hash Functions (for AWS SigV4):\n";
$hashAlgos = hash_algos();
echo "   SHA256 Support: " . (in_array('sha256', $hashAlgos) ? 'YES' : 'NO') . "\n";
echo "   HMAC Support: " . (function_exists('hash_hmac') ? 'YES' : 'NO') . "\n";

echo "\n=== Verification Complete ===\n";