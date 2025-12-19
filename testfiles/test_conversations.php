<?php
require_once 'src/Database.php';
require_once 'src/Auth.php';
require_once 'src/MessagingService.php';

use LagunaPartners\Database;
use LagunaPartners\MessagingService;

$db = Database::getInstance();

echo "=== ACCOUNT 481425 ===\n";
$account = $db->fetchOne('SELECT * FROM accounts WHERE id = 481425');
echo json_encode($account, JSON_PRETTY_PRINT) . "\n";

echo "\n=== USER_ACCOUNTS FOR ACCOUNT 481425 ===\n";
$ua = $db->fetchAll('SELECT * FROM user_accounts WHERE account_id = 481425');
echo json_encode($ua, JSON_PRETTY_PRINT) . "\n";

if (!empty($ua)) {
    $userId = $ua[0]['user_id'];
    echo "\n=== USER $userId ===\n";
    $user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
    echo json_encode($user, JSON_PRETTY_PRINT) . "\n";
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['active_account_id'] = 481425;
    $_SESSION['user_type'] = 'vendor';
    $_SESSION['logged_in'] = true;
    
    echo "\n=== TEST getUserConversations ===\n";
    $messagingService = new MessagingService();
    $conversations = $messagingService->getUserConversations($userId, 'vendor');
    echo json_encode($conversations, JSON_PRETTY_PRINT) . "\n";
}
