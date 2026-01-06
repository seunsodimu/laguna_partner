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

echo "Checking custbody_sales_rep field in POs\n";
echo "======================================\n\n";

try {
    $pos = $netsuite->getPurchaseOrders();
    
    if (!empty($pos)) {
        // Get a sample PO
        $firstPoId = $pos[0]['id'];
        
        echo "Fetching full details for PO ID: {$firstPoId}\n\n";
        $fullPo = $netsuite->getPurchaseOrder($firstPoId);
        
        echo "Sales Rep field structure:\n";
        echo "  custbody_sales_rep: " . json_encode($fullPo['custbody_sales_rep'] ?? 'NOT FOUND', JSON_PRETTY_PRINT) . "\n\n";
        
        // Check what users we have in the database
        echo "Users in database:\n";
        $users = $db->fetchAll("SELECT id, email, first_name, last_name, netsuite_id FROM users LIMIT 10");
        
        foreach ($users as $user) {
            $nsId = $user['netsuite_id'] ?? 'NULL';
            echo "  ID: {$user['id']}, NetSuite ID: {$nsId}, Email: {$user['email']}\n";
        }
        
    } else {
        echo "No POs found\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
