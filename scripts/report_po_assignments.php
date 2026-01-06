<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

$db = Database::getInstance();

echo "PO Assignment Report\n";
echo "====================\n\n";

// Get assignment counts by user
$assignments = $db->fetchAll(
    "SELECT 
        u.id,
        u.email,
        u.first_name,
        u.last_name,
        u.netsuite_id,
        COUNT(po.id) as po_count
    FROM users u
    LEFT JOIN purchase_orders po ON u.id = po.buyer_id
    WHERE u.netsuite_id IS NOT NULL
    GROUP BY u.id
    HAVING po_count > 0
    ORDER BY po_count DESC"
);

if (empty($assignments)) {
    echo "No assignments found\n";
    exit(0);
}

echo "Users with assigned POs:\n";
echo str_repeat("-", 80) . "\n";

$totalAssigned = 0;
foreach ($assignments as $assignment) {
    $fullName = trim("{$assignment['first_name']} {$assignment['last_name']}");
    printf("%-30s %-35s %4d POs\n",
        $fullName,
        $assignment['email'],
        $assignment['po_count']
    );
    $totalAssigned += $assignment['po_count'];
}

echo "\n" . str_repeat("-", 80) . "\n";

$totals = $db->fetchOne(
    "SELECT 
        COUNT(*) as total_pos,
        SUM(CASE WHEN buyer_id IS NOT NULL THEN 1 ELSE 0 END) as assigned_pos,
        SUM(CASE WHEN buyer_id IS NULL THEN 1 ELSE 0 END) as unassigned_pos
    FROM purchase_orders"
);

echo "\nOverall Statistics:\n";
echo "  Total POs: {$totals['total_pos']}\n";
echo "  Assigned POs: {$totals['assigned_pos']}\n";
echo "  Unassigned POs: {$totals['unassigned_pos']}\n";
echo "  Assignment Rate: " . round(($totals['assigned_pos'] / $totals['total_pos']) * 100, 1) . "%\n";

// Show a sample of assigned POs
echo "\n\nSample of assigned POs:\n";
echo str_repeat("-", 80) . "\n";

$samples = $db->fetchAll(
    "SELECT 
        po.tran_id,
        po.vendor_name,
        CONCAT(u.first_name, ' ', u.last_name) as assigned_to,
        po.status_text,
        po.created_date
    FROM purchase_orders po
    JOIN users u ON po.buyer_id = u.id
    ORDER BY po.created_date DESC
    LIMIT 10"
);

foreach ($samples as $sample) {
    printf("%-12s %-30s → %-25s [%s]\n",
        $sample['tran_id'],
        substr($sample['vendor_name'], 0, 30),
        substr($sample['assigned_to'], 0, 25),
        $sample['status_text']
    );
}
