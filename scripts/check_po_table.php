<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

$db = Database::getInstance();

$columns = $db->fetchAll("DESCRIBE purchase_orders");

echo "Purchase Orders Table Structure\n";
echo "===============================\n\n";

foreach ($columns as $col) {
    printf("%-30s %-20s Null: %-3s Key: %-3s Default: %s\n",
        $col['Field'],
        $col['Type'],
        $col['Null'],
        $col['Key'] ?? 'N/A',
        $col['Default'] ?? 'NULL'
    );
}
