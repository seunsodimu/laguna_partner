<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

$db = Database::getInstance();

echo "Checking assigned_user_id in POs\n";
echo "================================\n\n";

// Get a sample of POs with their assigned users
$pos = $db->fetchAll(
    "SELECT 
        po.id,
        po.tran_id,
        po.vendor_name,
        po.buyer_id,
        u.id as user_id,
        u.email as user_email,
        u.first_name,
        u.last_name,
        u.netsuite_id
    FROM purchase_orders po
    LEFT JOIN users u ON po.buyer_id = u.id
    LIMIT 5"
);

if (empty($pos)) {
    echo "No POs found\n";
    exit(0);
}

echo "Sample of POs and their assigned users:\n";
echo str_repeat("-", 100) . "\n";

foreach ($pos as $po) {
    $userDisplay = "Unassigned User";
    if ($po['buyer_id']) {
        $userDisplay = "{$po['first_name']} {$po['last_name']} ({$po['user_email']})";
    }
    
    printf("%-10s %-15s %-30s → %s\n",
        $po['tran_id'],
        substr($po['vendor_name'], 0, 15),
        "User ID: " . ($po['buyer_id'] ?? 'NULL'),
        $userDisplay
    );
}

// Count totals
$totals = $db->fetchOne(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN buyer_id IS NOT NULL THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN buyer_id IS NULL THEN 1 ELSE 0 END) as unassigned
    FROM purchase_orders"
);

echo "\nStatistics:\n";
echo "  Total POs: {$totals['total']}\n";
echo "  Assigned: {$totals['assigned']}\n";
echo "  Unassigned: {$totals['unassigned']}\n";
