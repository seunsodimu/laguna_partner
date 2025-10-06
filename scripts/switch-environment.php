#!/usr/bin/env php
<?php
/**
 * Switch NetSuite Environment
 * 
 * Usage: php scripts/switch-environment.php [production|sandbox]
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Check if environment argument is provided
if ($argc < 2) {
    echo "Usage: php scripts/switch-environment.php [production|sandbox]\n";
    echo "\nCurrent environment: " . ($_ENV['NETSUITE_ENVIRONMENT'] ?? 'sandbox') . "\n";
    exit(1);
}

$newEnv = strtolower($argv[1]);

// Validate environment
if (!in_array($newEnv, ['production', 'sandbox'])) {
    echo "Error: Invalid environment. Must be 'production' or 'sandbox'\n";
    exit(1);
}

// Get current environment
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    echo "Error: .env file not found\n";
    exit(1);
}

$envContent = file_get_contents($envFile);
$currentEnv = 'sandbox';

if (preg_match('/NETSUITE_ENVIRONMENT=(\w+)/', $envContent, $matches)) {
    $currentEnv = $matches[1];
}

// Check if already in the requested environment
if ($currentEnv === $newEnv) {
    echo "Already in $newEnv environment. No changes made.\n";
    exit(0);
}

// Confirm switch if going to production
if ($newEnv === 'production') {
    echo "\n⚠️  WARNING: You are about to switch to PRODUCTION environment!\n";
    echo "This will affect LIVE NetSuite data.\n\n";
    echo "Are you sure you want to continue? (yes/no): ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) !== 'yes') {
        echo "Operation cancelled.\n";
        exit(0);
    }
}

// Update .env file
$envContent = preg_replace(
    '/NETSUITE_ENVIRONMENT=\w+/',
    'NETSUITE_ENVIRONMENT=' . $newEnv,
    $envContent
);

if (file_put_contents($envFile, $envContent)) {
    echo "\n✓ Successfully switched to $newEnv environment\n\n";
    
    // Load credentials to show configuration
    $credentials = require __DIR__ . '/../config/credentials.php';
    $config = $credentials['netsuite'][$newEnv] ?? null;
    
    if ($config) {
        echo "Current Configuration:\n";
        echo "  Account ID: " . $config['account_id'] . "\n";
        echo "  Base URL:   " . $config['rest_url'] . "\n";
        echo "\n";
    }
    
    echo "⚠️  Important: Run a full sync to ensure data consistency:\n";
    echo "  php scripts/sync-accounts.php\n";
    echo "  php scripts/sync-purchase-orders.php\n";
    echo "  php scripts/sync-items.php\n";
    
    exit(0);
} else {
    echo "Error: Failed to update .env file. Check file permissions.\n";
    exit(1);
}