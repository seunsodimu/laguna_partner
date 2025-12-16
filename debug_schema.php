<?php
require_once 'src/Database.php';
use LagunaPartners\Database;

$db = Database::getInstance();

echo "=== ACCOUNTS TABLE STRUCTURE ===\n";
$result = $db->fetchAll('DESCRIBE accounts');
foreach($result as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ')\n';
}

echo "\n=== CONVERSATIONS TABLE STRUCTURE ===\n";
$result = $db->fetchAll('DESCRIBE conversations');
foreach($result as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ')\n';
}

echo "\n=== USER_ACCOUNTS TABLE STRUCTURE ===\n";
$result = $db->fetchAll('DESCRIBE user_accounts');
foreach($result as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ')\n';
}

echo "\n=== DATA CHECK ===\n";
$accounts = $db->fetchAll('SELECT id, company_name, email, is_active FROM accounts LIMIT 3');
echo "Sample accounts: " . json_encode($accounts, JSON_PRETTY_PRINT) . "\n";

$conversations = $db->fetchAll('SELECT * FROM conversations LIMIT 5');
echo "Conversations: " . json_encode($conversations, JSON_PRETTY_PRINT) . "\n";
