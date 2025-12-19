<?php
$envPath = __DIR__ . '/.env';
echo "Checking .env file at: " . $envPath . "\n";
echo "File exists: " . (file_exists($envPath) ? 'YES' : 'NO') . "\n";
echo "File readable: " . (is_readable($envPath) ? 'YES' : 'NO') . "\n";

if (file_exists($envPath)) {
    echo "File size: " . filesize($envPath) . " bytes\n";
    echo "\nFirst 10 lines:\n";
    $lines = file($envPath);
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo "  " . trim($lines[$i]) . "\n";
    }
}

echo "\n\nTesting Dotenv loading:\n";
require_once __DIR__ . '/vendor/autoload.php';

if (file_exists($envPath)) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    echo "Dotenv created\n";
    
    $dotenv->load();
    echo "Dotenv loaded\n";
    
    echo "NETSUITE_ENVIRONMENT after load: " . ($_ENV['NETSUITE_ENVIRONMENT'] ?? 'NOT SET') . "\n";
    echo "APP_DEBUG after load: " . ($_ENV['APP_DEBUG'] ?? 'NOT SET') . "\n";
}
