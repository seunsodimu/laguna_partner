<?php
/**
 * Debug script to test sync.php loading
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Step 1: Loading autoload...\n";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✓ Autoload loaded successfully\n\n";
} catch (Exception $e) {
    echo "✗ Autoload failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Step 2: Loading Database class...\n";
try {
    require_once __DIR__ . '/src/Database.php';
    echo "✓ Database class loaded\n\n";
} catch (Exception $e) {
    echo "✗ Database class failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Step 3: Loading Auth class...\n";
try {
    require_once __DIR__ . '/src/Auth.php';
    echo "✓ Auth class loaded\n\n";
} catch (Exception $e) {
    echo "✗ Auth class failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Step 4: Loading NetSuiteClient class...\n";
try {
    require_once __DIR__ . '/src/NetSuiteClient.php';
    echo "✓ NetSuiteClient class loaded\n\n";
} catch (Exception $e) {
    echo "✗ NetSuiteClient class failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Step 5: Loading SyncService class...\n";
try {
    require_once __DIR__ . '/src/SyncService.php';
    echo "✓ SyncService class loaded\n\n";
} catch (Exception $e) {
    echo "✗ SyncService class failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Step 6: Loading config...\n";
try {
    $config = require __DIR__ . '/config/config.php';
    echo "✓ Config loaded\n\n";
} catch (Exception $e) {
    echo "✗ Config failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Step 7: Creating NetSuiteClient instance...\n";
try {
    $client = new \LagunaPartners\NetSuiteClient();
    echo "✓ NetSuiteClient instance created\n\n";
} catch (Exception $e) {
    echo "✗ NetSuiteClient instantiation failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Step 8: Creating SyncService instance...\n";
try {
    $sync = new \LagunaPartners\SyncService($config);
    echo "✓ SyncService instance created\n\n";
} catch (Exception $e) {
    echo "✗ SyncService instantiation failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✓✓✓ All steps completed successfully! ✓✓✓\n";