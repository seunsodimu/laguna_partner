#!/usr/bin/env php
<?php
/**
 * Sync Accounts and Users from NetSuite
 * Run via cron: 0 2 * * * php /path/to/sync-accounts.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/NetSuiteClient.php';
require_once __DIR__ . '/../src/SyncService.php';

use LagunaPartners\SyncService;

echo "Starting account and user sync...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('-', 50) . "\n";

try {
    $sync = new SyncService();
    $result = $sync->syncAccountsAndUsers();
    
    if ($result['success']) {
        echo "✓ Sync completed successfully!\n";
        echo "\nStatistics:\n";
        echo "  Total Processed: {$result['stats']['processed']}\n";
        echo "  Created: {$result['stats']['created']}\n";
        echo "  Updated: {$result['stats']['updated']}\n";
        echo "  Failed: {$result['stats']['failed']}\n";
        
        if (isset($result['details'])) {
            echo "\nDetails:\n";
            echo "  Vendors: {$result['details']['vendors']['processed']} processed, ";
            echo "{$result['details']['vendors']['created']} created, ";
            echo "{$result['details']['vendors']['updated']} updated\n";
            
            echo "  Dealers: {$result['details']['dealers']['processed']} processed, ";
            echo "{$result['details']['dealers']['created']} created, ";
            echo "{$result['details']['dealers']['updated']} updated\n";
            
            echo "  Buyers: {$result['details']['buyers']['processed']} processed, ";
            echo "{$result['details']['buyers']['created']} created, ";
            echo "{$result['details']['buyers']['updated']} updated\n";
        }
        
        exit(0);
    } else {
        echo "✗ Sync failed!\n";
        echo "Error: {$result['error']}\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}