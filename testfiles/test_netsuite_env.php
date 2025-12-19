<?php
/**
 * Test script to verify NetSuiteClient environment loading
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/src/NetSuiteClient.php';

use LagunaPartners\NetSuiteClient;

echo "=== NetSuiteClient Environment Test ===\n\n";

echo "1. Checking ENV NETSUITE_ENVIRONMENT:\n";
$envVal = $_ENV['NETSUITE_ENVIRONMENT'] ?? 'NOT SET';
echo "   Value: " . $envVal . "\n\n";

echo "2. Instantiating NetSuiteClient...\n";
try {
    $client = new NetSuiteClient();
    echo "   Success!\n\n";
    
    echo "3. Using reflection to check environment property:\n";
    $reflection = new ReflectionClass($client);
    $envProperty = $reflection->getProperty('environment');
    $envProperty->setAccessible(true);
    $envValue = $envProperty->getValue($client);
    
    echo "   Client environment value: " . $envValue . "\n\n";
    
    if ($envValue === 'sandbox') {
        echo "   FAILED: Environment is sandbox (default), env var was not loaded!\n";
    } else {
        echo "   SUCCESS: Environment is " . $envValue . " (from .env file)\n";
    }
    
    echo "\n4. Checking credentials loaded:\n";
    $credProperty = $reflection->getProperty('credentials');
    $credProperty->setAccessible(true);
    $credentials = $credProperty->getValue($client);
    
    if (isset($credentials['netsuite'][$envValue])) {
        echo "   Credentials found for " . $envValue . " environment\n";
        echo "   Account ID: " . $credentials['netsuite'][$envValue]['account_id'] . "\n";
    } else {
        echo "   No credentials found for " . $envValue . " environment\n";
    }
    
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
}
