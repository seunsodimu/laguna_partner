<?php
/**
 * Email Templates Management
 * CRUD interface for managing email templates
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

session_start();

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

$templates = $db->fetchAll(
    "SELECT id, name, subject, created_at, updated_at FROM email_templates ORDER BY name"
);

$pageTitle = 'Email Templates';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Email Templates</h2>
            <p class="text-muted">Manage email templates for system notifications</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#testEmailModal">
                <i class="bi bi-envelope-check"></i> Test Email
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="openCreateModal()">
                <i class="bi bi-plus-circle"></i> New Template
            </button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Subject</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>
                <tbody id="templatesTable">
                    <?php if (empty($templates)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No templates yet. <a href="#" onclick="openCreateModal(); return false;">Create one</a></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($template['name']); ?></strong>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars(substr($template['subject'], 0, 50)); ?><?php echo strlen($template['subject']) > 50 ? '...' : ''; ?></small>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($template['created_at'])); ?></small>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($template['updated_at'])); ?></small>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="editTemplate(<?php echo $template['id']; ?>)" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteTemplate(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars(addslashes($template['name'])); ?>')" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">New Email Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="templateForm">
                    <input type="hidden" id="templateId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Template Name *</label>
                        <input type="text" class="form-control" id="templateName" required maxlength="100">
                        <small class="text-muted">e.g., invoice_approved, po_rejection, etc.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Subject *</label>
                        <input type="text" class="form-control" id="templateSubject" required>
                        <small class="text-muted">You can use variables like {{invoice_number}}, {{vendor_name}}, etc.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Body *</label>
                        <textarea class="form-control" id="templateBody" rows="12" required></textarea>
                        <small class="text-muted d-block mt-2">
                            <strong>Available variables:</strong><br>
                            {{invoice_number}}, {{vendor_name}}, {{amount_total}}, {{due_date}}, {{correction_reason}}, {{portal_link}}, etc.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()">Save Template</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Email Sending</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="testEmailForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Test Email Address *</label>
                        <input type="email" class="form-control" id="testEmailAddress" placeholder="admin@example.com" required>
                        <small class="text-muted">Enter an email address to receive the test email</small>
                    </div>
                    <div id="testEmailResult" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="sendTestEmailBtn" onclick="sendTestEmail()">Send Test Email</button>
            </div>
        </div>
    </div>
</div>

<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1050;"></div>

<script>
const BASE_PATH = '<?php echo BASE_PATH; ?>';

function openCreateModal() {
    document.getElementById('templateId').value = '';
    document.getElementById('templateName').value = '';
    document.getElementById('templateSubject').value = '';
    document.getElementById('templateBody').value = '';
    document.getElementById('templateModalTitle').textContent = 'New Email Template';
    new bootstrap.Modal(document.getElementById('templateModal')).show();
}

function editTemplate(id) {
    fetch(`${BASE_PATH}/api/email-templates.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('templateId').value = data.data.id;
                document.getElementById('templateName').value = data.data.name;
                document.getElementById('templateSubject').value = data.data.subject;
                document.getElementById('templateBody').value = data.data.body;
                document.getElementById('templateModalTitle').textContent = 'Edit Email Template';
                new bootstrap.Modal(document.getElementById('templateModal')).show();
            } else {
                showToast('Error: ' + (data.error || 'Failed to load template'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load template', 'error');
        });
}

function saveTemplate() {
    const id = document.getElementById('templateId').value;
    const name = document.getElementById('templateName').value.trim();
    const subject = document.getElementById('templateSubject').value.trim();
    const body = document.getElementById('templateBody').value.trim();
    
    if (!name || !subject || !body) {
        showToast('All fields are required', 'warning');
        return;
    }
    
    const action = id ? 'update' : 'create';
    const method = id ? 'PUT' : 'POST';
    const url = id ? `${BASE_PATH}/api/email-templates.php?action=${action}&id=${id}` : `${BASE_PATH}/api/email-templates.php?action=${action}`;
    
    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, subject, body })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
            showToast(data.message || 'Template saved successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + (data.error || 'Failed to save template'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save template', 'error');
    });
}

function deleteTemplate(id, name) {
    if (!confirm(`Are you sure you want to delete the template "${name}"?`)) {
        return;
    }
    
    fetch(`${BASE_PATH}/api/email-templates.php?action=delete&id=${id}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Template deleted successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + (data.error || 'Failed to delete template'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to delete template', 'error');
    });
}

function showToast(message, type = 'info') {
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
    
    setTimeout(() => toastElement.remove(), 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function sendTestEmail() {
    const email = document.getElementById('testEmailAddress').value.trim();
    const resultDiv = document.getElementById('testEmailResult');
    const sendBtn = document.getElementById('sendTestEmailBtn');
    
    if (!email) {
        showToast('Please enter an email address', 'warning');
        return;
    }
    
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    resultDiv.style.display = 'none';
    
    fetch(`${BASE_PATH}/api/email.php?action=test`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = 'Send Test Email';
        resultDiv.style.display = 'block';
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Success!</strong><br>
                    ${escapeHtml(data.message)}<br>
                    <small class="text-muted">Provider: ${escapeHtml(data.provider.toUpperCase())} | Time: ${escapeHtml(data.timestamp)}</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            showToast('Test email sent successfully', 'success');
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <strong>Error!</strong><br>
                    ${escapeHtml(data.message || 'Failed to send test email')}<br>
                    <small class="text-muted">Check the application logs for more details.</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            showToast('Failed to send test email', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        sendBtn.disabled = false;
        sendBtn.innerHTML = 'Send Test Email';
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Error!</strong><br>
                An unexpected error occurred while sending the test email.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        showToast('Failed to send test email', 'error');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
