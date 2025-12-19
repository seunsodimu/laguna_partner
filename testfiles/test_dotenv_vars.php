<?php
require_once __DIR__ . '/vendor/autoload.php';

echo "Testing where Dotenv puts variables:\n\n";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "1. In \$_ENV:\n";
echo "   NETSUITE_ENVIRONMENT: " . ($_ENV['NETSUITE_ENVIRONMENT'] ?? 'NOT FOUND') . "\n";
echo "   APP_DEBUG: " . ($_ENV['APP_DEBUG'] ?? 'NOT FOUND') . "\n\n";

echo "2. In \$_SERVER:\n";
echo "   NETSUITE_ENVIRONMENT: " . ($_SERVER['NETSUITE_ENVIRONMENT'] ?? 'NOT FOUND') . "\n";
echo "   APP_DEBUG: " . ($_SERVER['APP_DEBUG'] ?? 'NOT FOUND') . "\n\n";

echo "3. Using getenv():\n";
echo "   NETSUITE_ENVIRONMENT: " . (getenv('NETSUITE_ENVIRONMENT') ?: 'NOT FOUND') . "\n";
echo "   APP_DEBUG: " . (getenv('APP_DEBUG') ?: 'NOT FOUND') . "\n\n";

echo "4. Checking all keys in \$_ENV:\n";
$envKeys = array_keys($_ENV);
echo "   Total ENV vars: " . count($envKeys) . "\n";
echo "   Sample keys: " . implode(', ', array_slice($envKeys, 0, 5)) . "\n";
