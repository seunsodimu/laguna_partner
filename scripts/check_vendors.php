<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

$db = Database::getInstance();

echo "Vendors in Database\n";
echo "===================\n\n";

$vendors = $db->fetchAll("SELECT id, company_name FROM accounts WHERE type='vendor' ORDER BY id DESC LIMIT 10");

foreach ($vendors as $vendor) {
    echo "  ID: {$vendor['id']} - {$vendor['company_name']}\n";
}

echo "\n\nLooking for vendors from POs:\n";
$poVendors = [1379419, 1366821];

foreach ($poVendors as $vid) {
    $exists = $db->fetchOne("SELECT id FROM accounts WHERE id = ? AND type='vendor'", [$vid]);
    echo "  Vendor {$vid}: " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
}
