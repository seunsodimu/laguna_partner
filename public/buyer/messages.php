<?php
/**
 * Buyer/Accounting Messaging
 * Manage conversations with vendors
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/MessagingService.php';

use LagunaPartners\Auth;
use LagunaPartners\Database;
use LagunaPartners\MessagingService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

Auth::requireAuth(['user']);

$db = Database::getInstance();
$messagingService = new MessagingService();
$user = Auth::user();

$conversationId = $_GET['conversation_id'] ?? null;
$selectedConversation = null;
$messages = [];

if ($conversationId) {
    $selectedConversation = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
    if ($selectedConversation && ($selectedConversation['buyer_user_id'] == $user['id'] || ($selectedConversation['conversation_type'] === 'vendor_to_buyer' && !$selectedConversation['buyer_user_id']))) {
        $messages = $messagingService->getMessages($conversationId, 100, 0);
        $messagingService->markConversationAsRead($conversationId, $user['id']);
    }
}

$conversations = $messagingService->getUserConversations($user['id'], 'buyer');

$pageTitle = 'Messages';
include __DIR__ . '/../includes/header.php';
?>

<style>
    .messages-container {
        display: flex;
        height: calc(100vh - 200px);
        gap: 1rem;
    }
    
    .conversations-list {
        flex: 0 0 30%;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        overflow-y: auto;
        background-color: #f8f9fa;
    }
    
    .conversation-item {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .conversation-item:hover {
        background-color: #e9ecef;
    }
    
    .conversation-item.active {
        background-color: #0d6efd;
        color: white;
    }
    
    .conversation-item .unread-badge {
        display: inline-block;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        text-align: center;
        line-height: 24px;
        font-size: 0.75rem;
        font-weight: bold;
    }
    
    .conversation-item.active .unread-badge {
        background-color: white;
        color: #0d6efd;
    }
    
    .messages-panel {
        flex: 1;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        display: flex;
        flex-direction: column;
        background-color: white;
    }
    
    .messages-header {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
        background-color: #f8f9fa;
    }
    
    .messages-body {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    
    .message {
        margin-bottom: 1rem;
        padding: 0.75rem;
        border-radius: 0.25rem;
        max-width: 70%;
    }
    
    .message.sent {
        background-color: #0d6efd;
        color: white;
        margin-left: auto;
        text-align: right;
    }
    
    .message.received {
        background-color: #e9ecef;
        color: black;
        margin-right: auto;
    }
    
    .message-sender {
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
        opacity: 0.8;
    }
    
    .message-time {
        font-size: 0.75rem;
        margin-top: 0.25rem;
        opacity: 0.7;
    }
    
    .messages-footer {
        padding: 1rem;
        border-top: 1px solid #dee2e6;
        background-color: #f8f9fa;
    }
    
    .empty-state {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        text-align: center;
        color: #6c757d;
    }
    
    .message-input-group {
        display: flex;
        gap: 0.5rem;
    }
    
    .message-input-group textarea {
        min-height: 60px;
        resize: vertical;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="bi bi-chat-dots"></i> Messages from Vendors</h2>
        </div>
    </div>

    <div class="messages-container">
        <!-- Conversations List -->
        <div class="conversations-list">
            <div class="p-3 border-bottom">
                <h5 class="mb-0">Conversations</h5>
            </div>
            
            <div id="conversationsList">
                <?php if (empty($conversations)): ?>
                    <div class="p-3 text-center text-muted">
                        <p>No conversations yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item <?= $conv['id'] == $conversationId ? 'active' : '' ?>" 
                             onclick="loadConversation(<?= $conv['id'] ?>)">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-500">
                                        <?= htmlspecialchars($conv['vendor_name'] ?? 'Unknown Vendor') ?>
                                    </div>
                                    <div class="small text-truncate" style="opacity: 0.8;">
                                        <?= htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 50)) ?>
                                    </div>
                                    <div class="small" style="opacity: 0.7;">
                                        <?= $conv['last_message_at'] ? date('m/d/Y H:i', strtotime($conv['last_message_at'])) : 'No messages' ?>
                                    </div>
                                </div>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Messages Panel -->
        <div class="messages-panel">
            <?php if ($selectedConversation): ?>
                <!-- Header -->
                <div class="messages-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">
                                <?php
                                $vendor = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$selectedConversation['vendor_id']]);
                                echo htmlspecialchars($vendor['company_name'] ?? 'Vendor');
                                ?>
                            </h6>
                            <?php if ($selectedConversation['subject']): ?>
                                <small class="text-muted"><?= htmlspecialchars($selectedConversation['subject']) ?></small>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-sm btn-secondary" onclick="closeConversation(<?= $conversationId ?>)">
                            Close
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div class="messages-body" id="messagesBody">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <p>No messages yet. Waiting for the vendor to start.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?= $msg['sender_user_id'] == $user['id'] ? 'sent' : 'received' ?>">
                                <div class="message-sender">
                                    <?php
                                    if ($msg['sender_type'] === 'vendor') {
                                        echo 'Vendor';
                                    } else {
                                        echo 'You';
                                    }
                                    ?>
                                </div>
                                <div><?= htmlspecialchars($msg['message_text']) ?></div>
                                <div class="message-time">
                                    <?= date('m/d H:i', strtotime($msg['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Input -->
                <div class="messages-footer">
                    <form id="messageForm" onsubmit="sendMessage(event, <?= $conversationId ?>)">
                        <div class="message-input-group">
                            <textarea class="form-control" id="messageInput" placeholder="Type your message..." required></textarea>
                            <button type="submit" class="btn btn-primary" style="align-self: flex-end;">
                                <i class="bi bi-send"></i> Send
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div>
                        <i class="bi bi-chat-dots" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="mt-3">Select a conversation to view messages</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
async function loadConversation(convId) {
    window.location.href = '<?= htmlspecialchars(BASE_PATH) ?>/buyer/messages.php?conversation_id=' + convId;
}

async function sendMessage(e, convId) {
    e.preventDefault();
    
    const messageText = document.getElementById('messageInput').value;
    if (!messageText.trim()) return;
    
    try {
        const response = await fetch('<?= htmlspecialchars(BASE_PATH) ?>/api/messages.php?action=send_message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                conversation_id: convId,
                message: messageText
            })
        });
        
        const result = await response.json();
        if (result.success) {
            document.getElementById('messageInput').value = '';
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Failed to send message'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error sending message');
    }
}

async function closeConversation(convId) {
    if (!confirm('Close this conversation?')) return;
    
    try {
        const response = await fetch('<?= htmlspecialchars(BASE_PATH) ?>/api/messages.php?action=close_conversation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                conversation_id: convId
            })
        });
        
        const result = await response.json();
        if (result.success) {
            window.location.href = '<?= htmlspecialchars(BASE_PATH) ?>/buyer/messages.php';
        } else {
            alert('Error closing conversation');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error closing conversation');
    }
}

// Scroll to bottom of messages
const messagesBody = document.getElementById('messagesBody');
if (messagesBody) {
    messagesBody.scrollTop = messagesBody.scrollHeight;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
