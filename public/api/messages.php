<?php
/**
 * Messaging API
 * Handles conversations and messages between vendors and accounting/buyers
 */

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/MessagingService.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;
use LagunaPartners\MessagingService;

ini_set('error_log', __DIR__ . '/../../logs/php-errors.log');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance();
$messagingService = new MessagingService();

$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $parts = explode('/', trim($path, '/'));
    $action = end($parts);
}

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUser = Auth::user();
$userId = $currentUser['id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
$userType = $currentUser['type'] ?? 'user';

try {
    switch ($action) {
        case 'get_conversations':
            handleGetConversations($userId, $userType);
            break;

        case 'get_conversation':
            handleGetConversation($_GET['id'] ?? null, $userId);
            break;

        case 'get_messages':
            handleGetMessages($_GET['conversation_id'] ?? null, $_GET['limit'] ?? 50, $_GET['offset'] ?? 0, $userId);
            break;

        case 'send_message':
            handleSendMessage($userId, $userType, $_POST['conversation_id'] ?? null, $_POST['message'] ?? null);
            break;

        case 'create_conversation':
            handleCreateConversation($userId, $userType, $_POST['vendor_id'] ?? null, $_POST['conversation_type'] ?? null, $_POST['recipient_id'] ?? null, $_POST['subject'] ?? null);
            break;

        case 'mark_as_read':
            handleMarkAsRead($_POST['message_id'] ?? null, $userId);
            break;

        case 'mark_conversation_as_read':
            handleMarkConversationAsRead($_POST['conversation_id'] ?? null, $userId);
            break;

        case 'get_unread_count':
            handleGetUnreadCount($userId);
            break;

        case 'close_conversation':
            handleCloseConversation($_POST['conversation_id'] ?? null, $userId);
            break;

        case 'archive_conversation':
            handleArchiveConversation($_POST['conversation_id'] ?? null, $userId);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }

} catch (\Exception $e) {
    http_response_code(500);
    error_log("Messages API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function handleGetConversations($userId, $userType) {
    global $messagingService;
    $conversations = $messagingService->getUserConversations($userId, $userType);
    echo json_encode(['success' => true, 'data' => $conversations]);
}

function handleGetConversation($conversationId, $userId) {
    global $db;
    
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
        return;
    }

    $conversation = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
    
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversation not found']);
        return;
    }

    $userRecord = $db->fetchOne("SELECT type FROM users WHERE id = ?", [$userId]);
    $userType = $userRecord ? $userRecord['type'] : null;
    
    $userAccountId = null;
    if ($userType === 'vendor') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userAccountId = $_SESSION['active_account_id'] ?? null;
        if (!$userAccountId) {
            $auth = new Auth();
            $accounts = $auth->getUserAccounts($userId);
            $userAccountId = $accounts[0]['id'] ?? null;
        }
    }
    
    $canAccess = $conversation['vendor_id'] == $userAccountId
        || ($userType === 'accounting' && $conversation['conversation_type'] === 'vendor_to_accounting')
        || ($userType === 'buyer' && $conversation['conversation_type'] === 'vendor_to_buyer');

    if (!$canAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    echo json_encode(['success' => true, 'data' => $conversation]);
}

function handleGetMessages($conversationId, $limit, $offset, $userId) {
    global $db, $messagingService;
    
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
        return;
    }

    $conversation = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
    
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversation not found']);
        return;
    }

    $userRecord = $db->fetchOne("SELECT type FROM users WHERE id = ?", [$userId]);
    $userType = $userRecord ? $userRecord['type'] : null;
    
    $userAccountId = null;
    if ($userType === 'vendor') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userAccountId = $_SESSION['active_account_id'] ?? null;
        if (!$userAccountId) {
            $auth = new Auth();
            $accounts = $auth->getUserAccounts($userId);
            $userAccountId = $accounts[0]['id'] ?? null;
        }
    }
    
    $canAccess = $conversation['vendor_id'] == $userAccountId
        || ($userType === 'accounting' && $conversation['conversation_type'] === 'vendor_to_accounting')
        || ($userType === 'buyer' && $conversation['conversation_type'] === 'vendor_to_buyer');

    if (!$canAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $messagingService->markConversationAsRead($conversationId, $userId);
    $messages = $messagingService->getMessages($conversationId, $limit, $offset);
    
    echo json_encode(['success' => true, 'data' => $messages]);
}

function handleSendMessage($userId, $userType, $conversationId, $messageText) {
    global $db, $messagingService;
    
    if (!$conversationId || !$messageText) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Conversation ID and message required']);
        return;
    }

    $conversation = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
    
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversation not found']);
        return;
    }

    $userRecord = $db->fetchOne("SELECT type FROM users WHERE id = ?", [$userId]);
    $userRecordType = $userRecord ? $userRecord['type'] : null;
    
    $userAccountId = null;
    if ($userRecordType === 'vendor') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userAccountId = $_SESSION['active_account_id'] ?? null;
        if (!$userAccountId) {
            $auth = new Auth();
            $accounts = $auth->getUserAccounts($userId);
            $userAccountId = $accounts[0]['id'] ?? null;
        }
    }
    
    $canAccess = $conversation['vendor_id'] == $userAccountId
        || ($userRecordType === 'accounting' && $conversation['conversation_type'] === 'vendor_to_accounting')
        || ($userRecordType === 'buyer' && $conversation['conversation_type'] === 'vendor_to_buyer');

    error_log("handleSendMessage access check - userId: $userId, userRecordType: $userRecordType, vendorId: {$conversation['vendor_id']}, userAccountId: " . ($userAccountId ?? 'NULL') . ", convType: {$conversation['conversation_type']}, canAccess: " . ($canAccess ? 'true' : 'false'));

    if (!$canAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $message = $messagingService->sendMessage($conversationId, $userId, $userRecordType, $messageText);
    
    if ($message) {
        echo json_encode(['success' => true, 'data' => $message]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
}

function handleCreateConversation($userId, $userType, $vendorId, $conversationType, $recipientId, $subject) {
    global $messagingService, $db;
    
    if ($userType === 'vendor') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $vendorId = $_SESSION['active_account_id'] ?? null;
        
        if (!$vendorId) {
            $auth = new Auth();
            $accounts = $auth->getUserAccounts($userId);
            $vendorId = $accounts[0]['id'] ?? null;
        }
        
        if (!$vendorId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'User not associated with any vendor account']);
            return;
        }
        
        $otherUserId = $recipientId ?: null;
    } else {
        $otherUserId = $userId;
    }

    if (!$vendorId || !$conversationType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Vendor ID and conversation type required']);
        return;
    }

    $conversation = $messagingService->getOrCreateConversation($vendorId, $conversationType, $otherUserId, $subject);
    
    if ($conversation) {
        echo json_encode(['success' => true, 'data' => $conversation]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create conversation']);
    }
}

function handleMarkAsRead($messageId, $userId) {
    global $messagingService;
    
    if (!$messageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Message ID required']);
        return;
    }

    $success = $messagingService->markMessageAsRead($messageId, $userId);
    echo json_encode(['success' => $success]);
}

function handleMarkConversationAsRead($conversationId, $userId) {
    global $messagingService;
    
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
        return;
    }

    $success = $messagingService->markConversationAsRead($conversationId, $userId);
    echo json_encode(['success' => $success]);
}

function handleGetUnreadCount($userId) {
    global $messagingService;
    $counts = $messagingService->getUnreadCount($userId);
    echo json_encode(['success' => true, 'data' => $counts]);
}

function handleCloseConversation($conversationId, $userId) {
    global $db, $messagingService;
    
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
        return;
    }

    $conversation = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
    
    if (!$conversation || ($conversation['accounting_user_id'] != $userId && $conversation['buyer_user_id'] != $userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $success = $messagingService->closeConversation($conversationId);
    echo json_encode(['success' => $success]);
}

function handleArchiveConversation($conversationId, $userId) {
    global $db, $messagingService;
    
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Conversation ID required']);
        return;
    }

    $conversation = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
    
    if (!$conversation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Conversation not found']);
        return;
    }
    
    $userRecord = $db->fetchOne("SELECT type FROM users WHERE id = ?", [$userId]);
    $userType = $userRecord ? $userRecord['type'] : null;
    
    $userAccountId = null;
    if ($userType === 'vendor') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userAccountId = $_SESSION['active_account_id'] ?? null;
        if (!$userAccountId) {
            $auth = new Auth();
            $accounts = $auth->getUserAccounts($userId);
            $userAccountId = $accounts[0]['id'] ?? null;
        }
    }
    
    $canAccess = $conversation['vendor_id'] == $userAccountId
        || $conversation['accounting_user_id'] == $userId
        || $conversation['buyer_user_id'] == $userId;

    if (!$canAccess) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $success = $messagingService->archiveConversation($conversationId);
    echo json_encode(['success' => $success]);
}
?>
