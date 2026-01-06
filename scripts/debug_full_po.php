<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/NetSuiteClient.php';

use LagunaPartners\NetSuiteClient;

$netsuite = new NetSuiteClient();

echo "Checking full PO details structure\n";
echo "==================================\n\n";

try {
    // Get first PO from list
    $pos = $netsuite->getPurchaseOrders();
    if (!empty($pos)) {
        $firstPoId = $pos[0]['id'];
        echo "Fetching full details for PO ID: {$firstPoId}\n\n";
        
        $fullPo = $netsuite->getPurchaseOrder($firstPoId);
        
        echo "Entity field structure:\n";
        echo "  entity: " . json_encode($fullPo['entity'] ?? 'NOT FOUND', JSON_PRETTY_PRINT) . "\n\n";
        
        echo "Currency field structure:\n";
        echo "  currency: " . json_encode($fullPo['currency'] ?? 'NOT FOUND', JSON_PRETTY_PRINT) . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
