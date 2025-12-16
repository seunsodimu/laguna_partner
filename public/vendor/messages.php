<?php
/**
 * Vendor Messaging
 * Manage conversations with accounting and buyer teams
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

Auth::requireAuth(['vendor']);

$db = Database::getInstance();
$auth = new Auth();
$messagingService = new MessagingService();
$user = Auth::user();

$accounts = $auth->getUserAccounts($user['id']);
$activeAccountId = $_SESSION['active_account_id'] ?? ($accounts[0]['id'] ?? null);

$conversationId = $_GET['conversation_id'] ?? null;
$selectedConversation = null;
$messages = [];

if ($conversationId) {
    $selectedConversation = $db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
    if ($selectedConversation && $selectedConversation['vendor_id'] == $activeAccountId) {
        $messages = $messagingService->getMessages($conversationId, 100, 0);
        $messagingService->markConversationAsRead($conversationId, $user['id']);
    }
}

$conversations = $messagingService->getUserConversations($user['id'], 'vendor');

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
            <h2><i class="bi bi-chat-dots"></i> Messages</h2>
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
                                        <?php
                                        if ($conv['conversation_type'] === 'vendor_to_accounting') {
                                            echo 'Accounting Team';
                                        } else {
                                            echo 'Buyer Team';
                                        }
                                        ?>
                                    </div>
                                    <div class="small text-truncate" style="opacity: 0.8;">
                                        <?= htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 50)) ?>
                                    </div>
                                    <div class="small" style="opacity: 0.7;">
                                        <?= date('m/d/Y H:i', strtotime($conv['last_message_at'])) ?>
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

            <div class="p-3 border-top">
                <button class="btn btn-sm btn-primary w-100" data-bs-toggle="modal" data-bs-target="#newConversationModal">
                    <i class="bi bi-plus-circle"></i> New Conversation
                </button>
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
                                if ($selectedConversation['conversation_type'] === 'vendor_to_accounting') {
                                    echo 'Accounting Team';
                                } else {
                                    echo 'Buyer Team';
                                }
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
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?= $msg['sender_user_id'] == $user['id'] ? 'sent' : 'received' ?>">
                                <div class="message-sender">
                                    <?php
                                    if ($msg['sender_type'] === 'vendor') {
                                        echo 'You';
                                    } else {
                                        echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']);
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
                        <p class="mt-3">Select a conversation to start messaging</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Conversation Modal -->
<div class="modal fade" id="newConversationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Start New Conversation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newConversationForm" onsubmit="createConversation(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="conversationType" class="form-label">Conversation Type</label>
                        <select class="form-select" id="conversationType" required>
                            <option value="">Select team...</option>
                            <option value="vendor_to_accounting">Accounting Team</option>
                            <option value="vendor_to_buyer">Buyer Team</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="recipient" class="form-label">Recipient (Optional)</label>
                        <select class="form-select" id="recipient">
                            <option value="">Any team member</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject (Optional)</label>
                        <input type="text" class="form-control" id="subject" placeholder="e.g., PO #12345 Discussion">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Start Conversation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function loadConversation(convId) {
    window.location.href = '<?= htmlspecialchars(BASE_PATH) ?>/vendor/messages.php?conversation_id=' + convId;
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

async function createConversation(e) {
    e.preventDefault();
    
    const type = document.getElementById('conversationType').value;
    const recipientId = document.getElementById('recipient').value;
    const subject = document.getElementById('subject').value;
    
    if (!type) {
        alert('Please select a team');
        return;
    }
    
    try {
        const response = await fetch('<?= htmlspecialchars(BASE_PATH) ?>/api/messages.php?action=create_conversation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                conversation_type: type,
                recipient_id: recipientId,
                subject: subject
            })
        });
        
        const result = await response.json();
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('newConversationModal')).hide();
            window.location.href = '<?= htmlspecialchars(BASE_PATH) ?>/vendor/messages.php?conversation_id=' + result.data.id;
        } else {
            alert('Error: ' + (result.error || 'Failed to create conversation'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error creating conversation');
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
            window.location.href = '<?= htmlspecialchars(BASE_PATH) ?>/vendor/messages.php';
        } else {
            alert('Error closing conversation');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error closing conversation');
    }
}

document.getElementById('conversationType')?.addEventListener('change', async function() {
    const type = this.value;
    const recipientSelect = document.getElementById('recipient');
    
    if (!type) {
        recipientSelect.innerHTML = '<option value="">Any team member</option>';
        return;
    }
    
    recipientSelect.innerHTML = '<option value="">Loading...</option>';
    
    try {
        const response = await fetch('<?= htmlspecialchars(BASE_PATH) ?>/api/users.php?action=get_team_members&type=' + encodeURIComponent(type));
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            recipientSelect.innerHTML = '<option value="">Any team member</option>';
            result.data.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.first_name + ' ' + user.last_name;
                recipientSelect.appendChild(option);
            });
        } else {
            recipientSelect.innerHTML = '<option value="">Any team member</option>';
        }
    } catch (error) {
        console.error('Error:', error);
        recipientSelect.innerHTML = '<option value="">Any team member</option>';
    }
});

// Scroll to bottom of messages
const messagesBody = document.getElementById('messagesBody');
if (messagesBody) {
    messagesBody.scrollTop = messagesBody.scrollHeight;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
