<?php
require_once 'src/Database.php';
require_once 'src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== DEBUG: Vendor Conversations ===\n\n";

$db = Database::getInstance();

echo "1. All conversations in database:\n";
$allConversations = $db->fetchAll("SELECT id, vendor_id, conversation_type, subject, created_at FROM conversations ORDER BY created_at DESC LIMIT 5");
foreach ($allConversations as $conv) {
    echo "   - ID: {$conv['id']}, Vendor ID: {$conv['vendor_id']}, Type: {$conv['conversation_type']}, Created: {$conv['created_at']}\n";
}

echo "\n2. Accounts in database:\n";
$accounts = $db->fetchAll("SELECT id, company_name, type FROM accounts WHERE type = 'vendor' LIMIT 5");
foreach ($accounts as $acc) {
    echo "   - ID: {$acc['id']}, Company: {$acc['company_name']}\n";
}

echo "\n3. Users with vendor type:\n";
$vendorUsers = $db->fetchAll("SELECT u.id, u.email, u.type FROM users u WHERE u.type = 'vendor' LIMIT 5");
foreach ($vendorUsers as $user) {
    echo "   - ID: {$user['id']}, Email: {$user['email']}\n";
    
    $userAccounts = $db->fetchAll(
        "SELECT a.id, a.company_name FROM accounts a 
         INNER JOIN user_accounts ua ON a.id = ua.account_id 
         WHERE ua.user_id = ?",
        [$user['id']]
    );
    if ($userAccounts) {
        foreach ($userAccounts as $acc) {
            echo "     → Account: {$acc['id']} ({$acc['company_name']})\n";
        }
    } else {
        echo "     → No linked accounts\n";
    }
}

echo "\n4. Session info:\n";
if (Auth::check()) {
    $user = Auth::user();
    echo "   - User ID: {$user['id']}\n";
    echo "   - User Type: {$user['type']}\n";
    echo "   - Account ID (from session): {$user['account_id']}\n";
    echo "   - SESSION['active_account_id']: " . ($_SESSION['active_account_id'] ?? 'NOT SET') . "\n";
    
    echo "\n5. Testing getUserConversations logic:\n";
    
    if ($user['type'] === 'vendor') {
        $vendorId = $_SESSION['active_account_id'] ?? null;
        echo "   - Session vendor ID: " . ($vendorId ?? 'NULL') . "\n";
        
        if (!$vendorId) {
            $userAccounts = $db->fetchAll(
                "SELECT a.id FROM accounts a
                 INNER JOIN user_accounts ua ON a.id = ua.account_id
                 WHERE ua.user_id = ? AND a.type = 'vendor' AND a.is_active = 1",
                [$user['id']]
            );
            $vendorId = $userAccounts[0]['id'] ?? null;
            echo "   - Fallback vendor ID (from user_accounts): " . ($vendorId ?? 'NULL') . "\n";
        }
        
        if ($vendorId) {
            echo "\n   - Conversations for vendor_id = $vendorId:\n";
            $conversations = $db->fetchAll(
                "SELECT c.id, c.vendor_id, c.conversation_type, c.subject, c.created_at 
                 FROM conversations c 
                 WHERE c.vendor_id = ? 
                 ORDER BY c.created_at DESC",
                [$vendorId]
            );
            if ($conversations) {
                foreach ($conversations as $conv) {
                    echo "     • ID: {$conv['id']}, Type: {$conv['conversation_type']}, Subject: {$conv['subject']}\n";
                }
            } else {
                echo "     • No conversations found!\n";
            }
        } else {
            echo "   - ERROR: Could not determine vendor ID!\n";
        }
    }
} else {
    echo "   - User is NOT authenticated\n";
}

echo "\n";
?>
