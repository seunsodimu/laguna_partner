<?php
/**
 * Debug: Check what vendors are in PO data from NetSuite
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/NetSuiteClient.php';
require_once __DIR__ . '/../src/SyncService.php';

use LagunaPartners\Database;
use LagunaPartners\NetSuiteClient;
use LagunaPartners\SyncService;

$db = Database::getInstance();
$netsuite = new NetSuiteClient();
$syncService = new SyncService();

echo "Analyzing vendor IDs in PO data from NetSuite\n";
echo "=============================================\n\n";

// Get first 10 POs from NetSuite
echo "Fetching POs from NetSuite...\n";
try {
    $pos = $netsuite->getPurchaseOrders();
} catch (\Exception $e) {
    echo "Error fetching POs: " . $e->getMessage() . "\n";
    exit(1);
}

if (!$pos || empty($pos)) {
    echo "No POs returned from NetSuite\n";
    exit(0);
}

echo "Found " . count($pos) . " POs\n\n";

// Collect vendor IDs from POs
$vendorIds = [];
$vendorCheck = [];

foreach (array_slice($pos, 0, 10) as $po) {
    $vendorId = $po['entity']['id'] ?? null;
    $vendorName = $po['entity']['refName'] ?? 'Unknown';
    
    if ($vendorId) {
        $vendorIds[$vendorId] = $vendorName;
        
        // Check if this vendor exists in our database
        $accountCheck = $db->fetchOne(
            "SELECT id FROM accounts WHERE id = ? AND type = 'vendor'",
            [(int)$vendorId]
        );
        
        $userCheck = $db->fetchOne(
            "SELECT id FROM users WHERE netsuite_id = ? AND type = 'vendor'",
            [(int)$vendorId]
        );
        
        $vendorCheck[$vendorId] = [
            'in_accounts' => !!$accountCheck,
            'in_users' => !!$userCheck
        ];
    }
}

echo "Vendor IDs found in POs:\n";
echo str_repeat("-", 80) . "\n";

foreach ($vendorIds as $vendorId => $vendorName) {
    $check = $vendorCheck[$vendorId];
    $status = ($check['in_accounts'] || $check['in_users']) ? '✓' : '✗';
    echo sprintf("%s %-10s %-50s [accounts: %s, users: %s]\n",
        $status,
        $vendorId,
        substr($vendorName, 0, 48),
        $check['in_accounts'] ? 'Y' : 'N',
        $check['in_users'] ? 'Y' : 'N'
    );
}

echo "\nSummary:\n";
echo "- Vendors in database: " . count(array_filter($vendorCheck, fn($c) => $c['in_accounts'] || $c['in_users'])) . "\n";
echo "- Vendors NOT in database: " . count(array_filter($vendorCheck, fn($c) => !$c['in_accounts'] && !$c['in_users'])) . "\n";
