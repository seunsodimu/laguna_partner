#!/usr/bin/env php
<?php
/**
 * Sync Items from NetSuite
 * Run via cron: 0 */6 * * * php /path/to/sync-items.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/NetSuiteClient.php';
require_once __DIR__ . '/../src/SyncService.php';
require_once __DIR__ . '/../src/EmailService.php';

use LagunaPartners\SyncService;

echo "Starting item sync...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('-', 50) . "\n";

try {
    $sync = new SyncService();
    $result = $sync->syncItems();
    
    if ($result['success']) {
        echo "✓ Sync completed successfully!\n";
        echo "\nStatistics:\n";
        echo "  Total Processed: {$result['stats']['processed']}\n";
        echo "  Created: {$result['stats']['created']}\n";
        echo "  Updated: {$result['stats']['updated']}\n";
        echo "  Failed: {$result['stats']['failed']}\n";
        
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