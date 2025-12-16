<?php
/**
 * Teams Webhook Configuration
 * Manage Microsoft Teams webhook URLs for notifications
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

// Define base path for redirects
define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

session_start();

// Check authentication and user type
if (!Auth::check()) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$user = Auth::user();
if ($user['type'] !== 'user' || $user['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$db = Database::getInstance();

// Get all webhook configurations
$webhooks = $db->fetchAll(
    "SELECT id, notification_type, channel_name, is_active, description, updated_at FROM teams_webhook_config ORDER BY notification_type"
);

$pageTitle = 'Microsoft Teams Webhooks';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>Microsoft Teams Webhook Configuration</h2>
            <p class="text-muted">Configure webhook URLs for Microsoft Teams notifications</p>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle"></i>
        <strong>Setup Instructions:</strong>
        <ol class="mb-0 mt-2" style="font-size: 0.95rem;">
            <li>In Microsoft Teams, navigate to your channel</li>
            <li>Click <strong>"More options" (...)</strong> and select <strong>"Connectors"</strong></li>
            <li>Search for and select <strong>"Incoming Webhook"</strong></li>
            <li>Configure the webhook and copy the webhook URL</li>
            <li>Paste the URL below for the notification type you want to enable</li>
        </ol>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Webhook Configurations Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Notification Type</th>
                        <th>Channel Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webhooks as $webhook): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($webhook['notification_type']); ?></strong>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($webhook['channel_name'] ?? 'Not configured'); ?>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($webhook['description'] ?? ''); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $webhook['is_active'] ? 'success' : 'secondary'; ?>">
                                <?php echo $webhook['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo $webhook['updated_at'] ? date('M d, Y g:i A', strtotime($webhook['updated_at'])) : 'Never'; ?>
                            </small>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                    data-bs-target="#webhookModal" 
                                    onclick="editWebhook(<?php echo $webhook['id']; ?>)">
                                <i class="bi bi-pencil"></i> Configure
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Webhook Modal -->
<div class="modal fade" id="webhookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Webhook</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="webhookForm">
                    <input type="hidden" id="webhookId">
                    
                    <div class="mb-3">
                        <label for="notificationType" class="form-label">Notification Type</label>
                        <input type="text" class="form-control" id="notificationType" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="channelName" class="form-label">Channel Name</label>
                        <input type="text" class="form-control" id="channelName" 
                               placeholder="e.g., Purchase Orders">
                    </div>

                    <div class="mb-3">
                        <label for="channelId" class="form-label">Channel ID (Optional)</label>
                        <input type="text" class="form-control" id="channelId" 
                               placeholder="e.g., 19:xxx@thread.tacv2">
                    </div>

                    <div class="mb-3">
                        <label for="webhookUrl" class="form-label">Webhook URL <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="webhookUrl" rows="3" 
                                  placeholder="https://outlook.webhook.office.com/webhookb2/..." required></textarea>
                        <small class="form-text text-muted">
                            Keep this URL secret and secure. It provides access to your Teams channel.
                        </small>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="isActive">
                        <label class="form-check-label" for="isActive">
                            Enable this webhook
                        </label>
                    </div>

                    <div class="alert alert-warning" id="testMessage" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" onclick="testWebhook()">
                    <i class="bi bi-check-circle"></i> Test Webhook
                </button>
                <button type="button" class="btn btn-primary" onclick="saveWebhook()">
                    <i class="bi bi-check-lg"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const basePath = '<?php echo BASE_PATH; ?>';

async function editWebhook(webhookId) {
    try {
        const response = await fetch(`${basePath}/api/teams-webhook-config.php?action=get&id=${webhookId}`);
        const result = await response.json();
        
        if (!result.success) {
            alert('Error loading webhook: ' + result.error);
            return;
        }
        
        const data = result.data;
        document.getElementById('webhookId').value = data.id;
        document.getElementById('notificationType').value = data.notification_type;
        document.getElementById('channelName').value = data.channel_name || '';
        document.getElementById('channelId').value = data.channel_id || '';
        document.getElementById('isActive').checked = data.is_active == 1;
        document.getElementById('webhookUrl').value = '';
        document.getElementById('testMessage').style.display = 'none';
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function testWebhook() {
    const webhookUrl = document.getElementById('webhookUrl').value.trim();
    
    if (!webhookUrl) {
        alert('Please enter a webhook URL');
        return;
    }
    
    document.getElementById('testMessage').innerHTML = '<i class="bi bi-hourglass"></i> Testing webhook...';
    document.getElementById('testMessage').style.display = 'block';
    
    try {
        const response = await fetch(`${basePath}/api/teams-webhook-config.php?action=test`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                webhook_url: webhookUrl
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('testMessage').innerHTML = '<i class="bi bi-check-circle"></i> <strong>Success!</strong> ' + result.message;
            document.getElementById('testMessage').className = 'alert alert-success';
        } else {
            document.getElementById('testMessage').innerHTML = '<i class="bi bi-exclamation-triangle"></i> <strong>Error:</strong> ' + result.error;
            document.getElementById('testMessage').className = 'alert alert-danger';
        }
        document.getElementById('testMessage').style.display = 'block';
    } catch (error) {
        document.getElementById('testMessage').innerHTML = '<i class="bi bi-exclamation-triangle"></i> <strong>Error:</strong> ' + error.message;
        document.getElementById('testMessage').className = 'alert alert-danger';
        document.getElementById('testMessage').style.display = 'block';
    }
}

async function saveWebhook() {
    const webhookId = document.getElementById('webhookId').value;
    const webhookUrl = document.getElementById('webhookUrl').value.trim();
    const channelName = document.getElementById('channelName').value.trim();
    const channelId = document.getElementById('channelId').value.trim();
    const isActive = document.getElementById('isActive').checked ? 1 : 0;
    
    if (!webhookUrl) {
        alert('Please enter a webhook URL');
        return;
    }
    
    if (!channelName) {
        alert('Please enter a channel name');
        return;
    }
    
    try {
        const response = await fetch(`${basePath}/api/teams-webhook-config.php?action=update`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: webhookId,
                webhook_url: webhookUrl,
                channel_name: channelName,
                channel_id: channelId,
                is_active: isActive
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Webhook configuration saved successfully!');
            location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
