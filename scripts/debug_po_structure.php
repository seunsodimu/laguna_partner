<?php
/**
 * Debug: Inspect actual PO data structure from NetSuite
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/NetSuiteClient.php';

use LagunaPartners\NetSuiteClient;

$netsuite = new NetSuiteClient();

echo "Inspecting PO Data Structure\n";
echo "============================\n\n";

try {
    $pos = $netsuite->getPurchaseOrders();
    
    if (!empty($pos)) {
        echo "Analyzing first PO:\n";
        $firstPo = $pos[0];
        
        echo "\nFull PO data:\n";
        echo json_encode($firstPo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        
        echo "\n\nKey fields:\n";
        echo "- entity: " . json_encode($firstPo['entity'] ?? 'NOT FOUND') . "\n";
        echo "- tranId: " . ($firstPo['tranId'] ?? 'NOT FOUND') . "\n";
        echo "- id: " . ($firstPo['id'] ?? 'NOT FOUND') . "\n";
        echo "- internalId: " . ($firstPo['internalId'] ?? 'NOT FOUND') . "\n";
        
    } else {
        echo "No POs found\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
