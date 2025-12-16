<?php
require_once 'src/Database.php';

$db = \LagunaPartners\Database::getInstance();

echo "CONVERSATIONS:\n";
$convs = $db->fetchAll('SELECT id, vendor_id, conversation_type FROM conversations ORDER BY id DESC');
foreach ($convs as $c) {
    echo "  ID: {$c['id']}, vendor_id: {$c['vendor_id']}, type: {$c['conversation_type']}\n";
}

echo "\nACCOUNTS (vendor):\n";
$accts = $db->fetchAll('SELECT id, company_name FROM accounts WHERE type = "vendor"');
foreach ($accts as $a) {
    echo "  ID: {$a['id']}, {$a['company_name']}\n";
}

echo "\nUSER_ACCOUNTS:\n";
$ua = $db->fetchAll('SELECT user_id, account_id FROM user_accounts');
foreach ($ua as $u) {
    echo "  User {$u['user_id']} -> Account {$u['account_id']}\n";
}
?>
