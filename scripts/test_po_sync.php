<?php
/**
 * Test: Simulate PO sync for vendor 1335929
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env file (for Docker database)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/NetSuiteClient.php';
require_once __DIR__ . '/../src/SyncService.php';

use LagunaPartners\Database;
use LagunaPartners\SyncService;

$db = Database::getInstance();
$syncService = new SyncService();

echo "Testing PO Sync for Vendor 1335929\n";
echo "====================================\n\n";

// Test 1: Check vendor portal access
echo "1. Checking vendor portal access:\n";
$vendorId = 1335929;

$vendor = $db->fetchOne(
    "SELECT id FROM accounts WHERE id = ? AND type = 'vendor'",
    [$vendorId]
);

$user = $db->fetchOne(
    "SELECT id FROM users WHERE netsuite_id = ? AND type = 'vendor'",
    [$vendorId]
);

if ($vendor) {
    echo "   ✓ Vendor found in accounts table\n";
}

if ($user) {
    echo "   ✓ Vendor found in users table (via netsuite_id)\n";
}

if (!$vendor && !$user) {
    echo "   ✗ Vendor NOT found\n";
}

echo "\n";

// Test 2: Try to sync POs
echo "2. Attempting to sync purchase orders:\n";

try {
    $result = $syncService->syncPurchaseOrders(null, 100);
    
    if ($result['success']) {
        echo "   ✓ Sync completed successfully\n";
        echo "   - Total available: " . $result['stats']['total_available'] . "\n";
        echo "   - Processed: " . $result['stats']['processed'] . "\n";
        echo "   - Created: " . $result['stats']['created'] . "\n";
        echo "   - Updated: " . $result['stats']['updated'] . "\n";
        echo "   - Failed: " . $result['stats']['failed'] . "\n";
        echo "   - Vendor not in portal: " . $result['stats']['vendor_not_in_portal'] . "\n";
        echo "   - Skipped: " . $result['stats']['skipped'] . "\n";
    } else {
        echo "   ✗ Sync failed: " . $result['error'] . "\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Exception during sync: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n";

// Test 3: Check if PO was synced
echo "3. Checking if PO 45723 was synced:\n";
$po = $db->fetchOne(
    "SELECT id, tran_id, vendor_id, vendor_name, status FROM purchase_orders WHERE id = 607632 OR tran_id LIKE '%45723%'",
    []
);

if ($po) {
    echo "   ✓ PO found in database\n";
    echo "   - ID: {$po['id']}\n";
    echo "   - Tran ID: {$po['tran_id']}\n";
    echo "   - Vendor ID: {$po['vendor_id']}\n";
    echo "   - Vendor Name: {$po['vendor_name']}\n";
    echo "   - Status: {$po['status']}\n";
} else {
    echo "   ✗ PO NOT found in database\n";
}

echo "\n";

// Test 4: Check logs
echo "4. Recent error logs:\n";
$logFile = __DIR__ . '/../logs/app-' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recent = array_slice($lines, -10);
    foreach ($recent as $line) {
        echo "   " . trim($line) . "\n";
    }
} else {
    echo "   No log file found for today\n";
}
