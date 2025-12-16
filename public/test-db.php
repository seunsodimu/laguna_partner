<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$host = $_ENV['DB_HOST'] ?? 'db';
$port = $_ENV['DB_PORT'] ?? 3306;
$db = $_ENV['DB_NAME'] ?? 'laguna_partner';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

echo "Attempting to connect to: $host:$port\n";
echo "Database: $db\n";
echo "User: $user\n\n";

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4;unix_socket=";
    echo "DSN: $dsn\n\n";
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Connected successfully!\n";
    
    $result = $pdo->query("SELECT 1 as test");
    $row = $result->fetch();
    echo "✅ Query executed: " . print_r($row, true);
    
} catch (Exception $e) {
    echo "❌ Connection failed:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
?>
