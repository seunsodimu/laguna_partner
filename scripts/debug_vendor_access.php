<?php
/**
 * Debug: Check vendor portal access for PO syncing
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env file (for Docker database)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    echo "Loaded .env file\n";
}

// Show current database config
echo "Database Configuration:\n";
echo "  Host: " . (getenv('DB_HOST') ?: 'localhostr') . "\n";
echo "  Port: " . (getenv('DB_PORT') ?: '3306') . "\n";
echo "  Database: " . (getenv('DB_NAME') ?: 'laguna_partner') . "\n";
echo "  User: " . (getenv('DB_USER') ?: 'root') . "\n";
echo "\n";

require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

$db = Database::getInstance();

$vendorId = 1335929;

echo "Checking vendor access for vendor ID: {$vendorId}\n";
echo "============================================\n\n";

// Check accounts table (any type)
echo "1. Checking accounts table:\n";
$account = $db->fetchOne(
    "SELECT id, type, company_name, is_active FROM accounts WHERE id = ?",
    [$vendorId]
);

if ($account) {
    echo "   ✓ Found in accounts table\n";
    echo "   - Type: {$account['type']}\n";
    echo "   - Company: {$account['company_name']}\n";
    echo "   - Is Active: " . ($account['is_active'] ? 'Yes' : 'No') . "\n";
} else {
    echo "   ✗ NOT found in accounts table\n";
}

echo "\n";

// Check users table
echo "2. Checking users table:\n";
$users = $db->fetchAll(
    "SELECT id, email, type, netsuite_id, is_active FROM users WHERE netsuite_id = ?",
    [$vendorId]
);

if (!empty($users)) {
    echo "   ✓ Found " . count($users) . " user(s) with netsuite_id={$vendorId}\n";
    foreach ($users as $user) {
        echo "   - ID: {$user['id']}, Email: {$user['email']}, Type: {$user['type']}, Active: " . ($user['is_active'] ? 'Yes' : 'No') . "\n";
    }
} else {
    echo "   ✗ No users found with netsuite_id={$vendorId}\n";
}

echo "\n";

// Show sample vendors that exist
echo "3. Sample vendors in accounts table:\n";
$sampleAccounts = $db->fetchAll(
    "SELECT id, type, company_name FROM accounts WHERE type = 'vendor' LIMIT 5",
    []
);
if (!empty($sampleAccounts)) {
    echo "   Found " . count($sampleAccounts) . " vendors:\n";
    foreach ($sampleAccounts as $acc) {
        echo "   - ID: {$acc['id']}, Company: {$acc['company_name']}\n";
    }
} else {
    echo "   No vendors in accounts table\n";
}

echo "\n";

// Show sample vendor users
echo "4. Sample vendor users in users table:\n";
$sampleUsers = $db->fetchAll(
    "SELECT id, email, type, netsuite_id FROM users WHERE type = 'vendor' LIMIT 5",
    []
);
if (!empty($sampleUsers)) {
    echo "   Found " . count($sampleUsers) . " vendor users:\n";
    foreach ($sampleUsers as $u) {
        echo "   - ID: {$u['id']}, Email: {$u['email']}, NetSuite ID: " . ($u['netsuite_id'] ?: 'NULL') . "\n";
    }
} else {
    echo "   No vendor users in users table\n";
}

echo "\n";

// Portal access check (current logic)
echo "5. Portal access check (with relaxed is_active requirement):\n";
$vendorCheck = $db->fetchOne(
    "SELECT id FROM accounts WHERE id = ? AND type = 'vendor'",
    [$vendorId]
);

$userCheck = $db->fetchOne(
    "SELECT id FROM users WHERE netsuite_id = ? AND type = 'vendor'",
    [$vendorId]
);

if ($vendorCheck || $userCheck) {
    echo "   ✓ Vendor HAS portal access\n";
} else {
    echo "   ✗ Vendor DOES NOT have portal access\n";
}

echo "\n";

// Check the PO
echo "6. Checking PO 45723:\n";
$po = $db->fetchOne(
    "SELECT id, vendor_id, vendor_name, status FROM purchase_orders WHERE id = 607632 OR tran_id LIKE '%45723%'",
    []
);

if ($po) {
    echo "   ✓ PO found\n";
    echo "   - ID: {$po['id']}\n";
    echo "   - Vendor ID: {$po['vendor_id']}\n";
    echo "   - Vendor Name: {$po['vendor_name']}\n";
    echo "   - Status: {$po['status']}\n";
} else {
    echo "   ✗ PO NOT found in database\n";
}
