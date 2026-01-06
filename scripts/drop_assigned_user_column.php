<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

require_once __DIR__ . '/../src/Database.php';

use LagunaPartners\Database;

$db = Database::getInstance();

try {
    // Drop foreign key first
    try {
        $db->query("ALTER TABLE purchase_orders DROP FOREIGN KEY purchase_orders_ibfk_1");
        echo "✓ Dropped foreign key\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'Constraint not found') === false) {
            echo "Warning: " . $e->getMessage() . "\n";
        }
    }
    
    // Drop index
    try {
        $db->query("ALTER TABLE purchase_orders DROP INDEX idx_assigned_user_id");
        echo "✓ Dropped index\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'no such index') === false) {
            echo "Warning: " . $e->getMessage() . "\n";
        }
    }
    
    // Drop column
    $db->query("ALTER TABLE purchase_orders DROP COLUMN assigned_user_id");
    echo "✓ Dropped assigned_user_id column\n";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        echo "✓ Column already dropped or doesn't exist\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
