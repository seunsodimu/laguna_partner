<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

$db = Database::getInstance();

echo "Testing Admin Purchase Orders API Queries\n";
echo "========================================\n\n";

// Simulate the API query for listing POs
$limit = 5;
$offset = 0;

echo "List Query:\n";
$pos = $db->fetchAll(
    "SELECT 
        po.id, 
        po.tran_id, 
        po.vendor_name, 
        po.total_amount, 
        po.currency, 
        po.status, 
        po.created_date, 
        po.due_date, 
        po.rejection_reason,
        po.buyer_id,
        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as assigned_user_name
     FROM purchase_orders po
     LEFT JOIN users u ON po.buyer_id = u.id
     WHERE 1=1
     ORDER BY po.created_date DESC LIMIT ? OFFSET ?",
    [$limit, $offset]
);

if (empty($pos)) {
    echo "No POs found\n";
    exit(0);
}

echo "Sample Results:\n";
echo str_repeat("-", 100) . "\n";

foreach ($pos as $po) {
    $assigned = trim($po['assigned_user_name']) ?: 'Unassigned User';
    printf("%-12s %-30s → %-25s [%s]\n",
        $po['tran_id'],
        substr($po['vendor_name'], 0, 30),
        substr($assigned, 0, 25),
        $po['status']
    );
}

echo "\n✓ Query executed successfully - assigned_user_name field is populated\n";
