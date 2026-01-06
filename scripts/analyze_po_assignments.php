<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/NetSuiteClient.php';

use LagunaPartners\Database;
use LagunaPartners\NetSuiteClient;

$db = Database::getInstance();
$netsuite = new NetSuiteClient();

echo "Analyzing PO Assignment Potential\n";
echo "=================================\n\n";

// Get all users with netsuite_id
$users = $db->fetchAll("SELECT id, netsuite_id, email FROM users WHERE netsuite_id IS NOT NULL");
$userNetsuiteIds = array_column($users, 'netsuite_id');

echo "Users in system with NetSuite IDs:\n";
foreach ($users as $user) {
    echo "  NetSuite ID {$user['netsuite_id']}: {$user['email']}\n";
}

echo "\n\nAnalyzing PO sales reps in NetSuite...\n";

try {
    $pos = $netsuite->getPurchaseOrders();
    
    $salesRepIds = [];
    $assignableCount = 0;
    
    // Sample 20 POs to check their sales reps
    $sample = array_slice($pos, 0, 20);
    
    foreach ($sample as $po) {
        $poId = $po['id'];
        $poTranId = $po['tranId'] ?? $po['id'];
        
        try {
            $fullPo = $netsuite->getPurchaseOrder($poId);
            
            if (isset($fullPo['custbody_sales_rep']) && is_array($fullPo['custbody_sales_rep'])) {
                $salesRepId = $fullPo['custbody_sales_rep']['id'] ?? null;
                $salesRepName = $fullPo['custbody_sales_rep']['refName'] ?? 'Unknown';
                
                if ($salesRepId) {
                    if (!isset($salesRepIds[$salesRepId])) {
                        $salesRepIds[$salesRepId] = [
                            'name' => $salesRepName,
                            'count' => 0,
                            'inSystem' => in_array($salesRepId, $userNetsuiteIds)
                        ];
                    }
                    $salesRepIds[$salesRepId]['count']++;
                    
                    if (in_array($salesRepId, $userNetsuiteIds)) {
                        $assignableCount++;
                    }
                }
            }
        } catch (\Exception $e) {
            echo "  Error fetching PO {$poId}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nSales Reps found in sampled POs:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($salesRepIds as $id => $info) {
        $status = $info['inSystem'] ? "✓ IN SYSTEM" : "✗ NOT IN SYSTEM";
        printf("  NetSuite ID %3s (%s): %s\n", $id, $info['name'], $status);
    }
    
    echo "\n\nSummary:\n";
    echo "  Total sales reps in sampled POs: " . count($salesRepIds) . "\n";
    echo "  Sales reps already in system: " . count(array_filter($salesRepIds, fn($r) => $r['inSystem'])) . "\n";
    echo "  POs that can be assigned: {$assignableCount} out of 20 sampled\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
