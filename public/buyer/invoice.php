<?php
/**
 * Invoice Details Page
 * Dedicated page for reviewing individual invoices
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

use LagunaPartners\Auth;
use LagunaPartners\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

session_start();

if (!Auth::check()) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

function getNetSuiteLink($invoice) {
    $env = strtoupper($_ENV['NETSUITE_ENVIRONMENT'] ?? 'SANDBOX');
    $account_key = 'NETSUITE_' . $env . '_ACCOUNT_ID';
    $account_id = $_ENV[$account_key] ?? '11134099_SB1';
    
    if ($invoice['netsuite_bill_id']) {
        return 'https://' . $account_id . '.app.netsuite.com/app/accounting/transactions/vendbill.nl?id=' . htmlspecialchars($invoice['netsuite_bill_id']);
    }
    return '#';
}

function getPostingPeriodOptions() {
    $options = [];
    $baseId = 46;
    $baseDate = new DateTime('2025-12-01');
    $now = new DateTime();
    $endDate = clone $now;
    $endDate->modify('+1 year');
    
    $current = clone $baseDate;
    $currentId = $baseId;
    
    while ($current <= $endDate) {
        $label = $current->format('F Y');
        $options[] = [
            'id' => $currentId,
            'label' => $label
        ];
        $current->modify('+1 month');
        $currentId++;
    }
    
    return $options;
}

$user = Auth::user();
if ($user['type'] !== 'user' || !in_array($user['role'], ['buyer', 'accounting', 'admin'])) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$db = Database::getInstance();
$invoice_id = intval($_GET['id'] ?? 0);

if (!$invoice_id) {
    header('Location: ' . BASE_PATH . '/buyer/invoices.php');
    exit;
}

$invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);
if (!$invoice) {
    header('Location: ' . BASE_PATH . '/buyer/invoices.php');
    exit;
}

$invoice['line_items'] = $db->fetchAll("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY line_number", [$invoice_id]);
$invoice['attachments'] = $db->fetchAll("SELECT * FROM invoice_attachments WHERE invoice_id = ?", [$invoice_id]);
$invoice['notes'] = $db->fetchAll("SELECT * FROM invoice_notes WHERE invoice_id = ? ORDER BY created_at DESC", [$invoice_id]);

$pageTitle = 'Invoice #' . htmlspecialchars($invoice['invoice_number']);
include __DIR__ . '/../includes/header.php';
?>

<style>
    .invoice-container {
        max-width: 1000px;
        margin: 30px auto;
    }
    
    .invoice-header {
        background: #0d47a1;
        color: white;
        padding: 30px;
        border-radius: 8px 8px 0 0;
        margin-bottom: 0;
    }
    
    .invoice-header h1 {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
    }
    
    .invoice-header p {
        margin: 5px 0 0 0;
        opacity: 0.9;
    }
    
    .invoice-details {
        background: white;
        border-radius: 0 0 8px 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .detail-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .detail-row:last-of-type {
        border-bottom: none;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
    }
    
    .detail-label {
        font-weight: 600;
        color: #666;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }
    
    .detail-value {
        font-size: 1rem;
        color: #333;
    }
    
    .section-title {
        background: #f8f9fa;
        padding: 15px 20px;
        font-weight: 700;
        font-size: 1.1rem;
        border-top: 1px solid #e9ecef;
        border-bottom: 2px solid var(--primary);
        margin-top: 20px;
        margin-bottom: 0;
    }
    
    .line-items-table {
        margin: 20px;
    }
    
    .attachments-list,
    .notes-list {
        padding: 20px;
    }
    
    .attachment-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 6px;
        margin-bottom: 10px;
    }
    
    .note-item {
        padding: 15px;
        background: #f8f9fa;
        border-left: 4px solid var(--primary);
        border-radius: 4px;
        margin-bottom: 15px;
    }
    
    .note-meta {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 8px;
    }
    
    .action-buttons {
        padding: 20px;
        display: flex;
        gap: 10px;
        justify-content: center;
        background: #f8f9fa;
    }
    
    .badge-large {
        padding: 0.5rem 1rem;
        font-size: 0.95rem;
    }
</style>

<div class="invoice-container">
    <div class="invoice-header">
        <h1>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
        <p><?php echo htmlspecialchars($invoice['vendor_name'] ?? 'N/A'); ?></p>
    </div>
    
    <div class="invoice-details">
        <div class="detail-row">
            <div class="detail-item">
                <span class="detail-label">Invoice Date</span>
                <span class="detail-value"><?php echo $invoice['invoice_date'] ? date('M d, Y', strtotime($invoice['invoice_date'])) : 'N/A'; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Due Date</span>
                <span class="detail-value"><?php echo $invoice['due_date'] ? date('M d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Amount</span>
                <span class="detail-value" style="font-size: 1.3rem; font-weight: 700; color: var(--primary);">
                    $<?php echo number_format($invoice['amount_total'] ?? 0, 2); ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <?php
                    $statusMap = [
                        'draft' => 'secondary',
                        'submitted' => 'info',
                        'under_review' => 'warning',
                        'approved' => 'success',
                        'processing' => 'primary',
                        'paid' => 'success',
                        'rejected' => 'danger'
                    ];
                    $statusClass = $statusMap[$invoice['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?php echo $statusClass; ?> badge-large">
                        <?php echo ucfirst(str_replace('_', ' ', $invoice['status'])); ?>
                    </span>
                </span>
            </div>
        </div>
        
        <div class="detail-row">
            <div class="detail-item">
                <span class="detail-label">Invoice Type</span>
                <span class="detail-value">
                    <?php echo $invoice['invoice_type'] === 'down_payment' ? 'Down Payment' : 'Regular Invoice'; ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Currency</span>
                <span class="detail-value"><?php echo htmlspecialchars($invoice['currency'] ?? 'USD'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Terms</span>
                <span class="detail-value"><?php echo htmlspecialchars($invoice['payment_terms'] ?? 'N/A'); ?></span>
            </div>
            <?php if ($invoice['estimated_payment_date']): ?>
            <div class="detail-item">
                <span class="detail-label">Est. Payment Date</span>
                <span class="detail-value"><?php echo date('M d, Y', strtotime($invoice['estimated_payment_date'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($invoice['description']): ?>
        <div class="detail-row">
            <div class="detail-item" style="grid-column: 1 / -1;">
                <span class="detail-label">Notes</span>
                <span class="detail-value"><?php echo htmlspecialchars($invoice['description']); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Department, Location, and Posting Period Fields -->
        <?php if (in_array($invoice['status'], ['submitted', 'under_review', 'approved'])): ?>
        <div class="section-title">
            <i class="bi bi-gear me-2"></i> NetSuite Sync Details
        </div>
        <div class="detail-row">
            <div class="detail-item">
                <label class="detail-label" for="department_id">
                    Department <span style="color: red;">*</span>
                </label>
                <select id="department_id" class="form-control" required>
                    <option value="">-- Select Department --</option>
                    <option value="1" <?php echo $invoice['department_id'] == 1 ? 'selected' : ''; ?>>Sales: Direct</option>
                    <option value="2" <?php echo $invoice['department_id'] == 2 ? 'selected' : ''; ?>>Sales: Metal</option>
                    <option value="3" <?php echo $invoice['department_id'] == 3 ? 'selected' : ''; ?>>Sales: Wholesale</option>
                    <option value="4" <?php echo $invoice['department_id'] == 4 ? 'selected' : ''; ?>>Corporate</option>
                    <option value="5" <?php echo $invoice['department_id'] == 5 ? 'selected' : ''; ?>>Sale</option>
                </select>
            </div>
            <div class="detail-item">
                <label class="detail-label" for="location_id">
                    Location <span style="color: red;">*</span>
                </label>
                <select id="location_id" class="form-control" required>
                    <option value="">-- Select Location --</option>
                    <option value="1" <?php echo $invoice['location_id'] == 1 ? 'selected' : ''; ?>>Laguna Texas</option>
                    <option value="2" <?php echo $invoice['location_id'] == 2 ? 'selected' : ''; ?>>Laguna South Carolina</option>
                    <option value="3" <?php echo $invoice['location_id'] == 3 ? 'selected' : ''; ?>>Laguna Michigan</option>
                    <option value="4" <?php echo $invoice['location_id'] == 4 ? 'selected' : ''; ?>>Laguna California</option>
                    <option value="5" <?php echo $invoice['location_id'] == 5 ? 'selected' : ''; ?>>Laguna International</option>
                    <option value="6" <?php echo $invoice['location_id'] == 6 ? 'selected' : ''; ?>>Laguna Demo/Returns/Damages</option>
                    <option value="7" <?php echo $invoice['location_id'] == 7 ? 'selected' : ''; ?>>Laguna Texas Outlet</option>
                    <option value="8" <?php echo $invoice['location_id'] == 8 ? 'selected' : ''; ?>>Laguna Texas Showroom</option>
                    <option value="9" <?php echo $invoice['location_id'] == 9 ? 'selected' : ''; ?>>Laguna South Carolina Showroom</option>
                </select>
            </div>
            <div class="detail-item">
                <label class="detail-label" for="posting_period_id">
                    Posting Period <span style="color: red;">*</span>
                </label>
                <select id="posting_period_id" class="form-control" required>
                    <option value="">-- Select Posting Period --</option>
                    <?php 
                    $periods = getPostingPeriodOptions();
                    foreach ($periods as $period): 
                    ?>
                    <option value="<?php echo $period['id']; ?>" <?php echo $invoice['postingperiod'] == $period['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($period['label']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Line Items -->
        <?php if (!empty($invoice['line_items'])): ?>
        <div class="section-title">
            <i class="bi bi-list-check me-2"></i> Line Items
        </div>
        <div class="line-items-table">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoice['line_items'] as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['description'] ?? '-'); ?></td>
                            <td class="text-end"><?php echo number_format($item['quantity'] ?? 0, 2); ?></td>
                            <td class="text-end">$<?php echo number_format($item['unit_price'] ?? 0, 2); ?></td>
                            <td class="text-end"><strong>$<?php echo number_format($item['amount'] ?? 0, 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Attachments -->
        <?php if (!empty($invoice['attachments'])): ?>
        <div class="section-title">
            <i class="bi bi-file-earmark me-2"></i> Attachments (<?php echo count($invoice['attachments']); ?>)
        </div>
        <div class="attachments-list">
            <?php foreach ($invoice['attachments'] as $attachment): ?>
            <div class="attachment-item">
                <div>
                    <i class="bi bi-file-earmark"></i>
                    <strong><?php echo htmlspecialchars($attachment['file_name']); ?></strong>
                    <small class="text-muted ms-2">
                        (<?php 
                        $size = $attachment['file_size'] ?? 0;
                        if ($size === 0) echo '0 Bytes';
                        else {
                            $k = 1024;
                            $sizes = ['Bytes', 'KB', 'MB'];
                            $i = floor(log($size) / log($k));
                            echo round($size / pow($k, $i) * 100) / 100 . ' ' . $sizes[$i];
                        }
                        ?>)
                    </small>
                </div>
                <a href="<?php echo BASE_PATH; ?>/api/download-invoice-attachment.php?id=<?php echo $attachment['id']; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download"></i> Download
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Notes -->
        <?php if (!empty($invoice['notes'])): ?>
        <div class="section-title">
            <i class="bi bi-chat-left-text me-2"></i> Notes (<?php echo count($invoice['notes']); ?>)
        </div>
        <div class="notes-list">
            <?php foreach ($invoice['notes'] as $note): ?>
            <div class="note-item">
                <div class="note-meta">
                    <strong><?php echo htmlspecialchars($note['user_name'] ?? 'Unknown'); ?></strong>
                    <span class="text-muted ms-2">
                        <?php echo date('M d, Y \a\t g:i A', strtotime($note['created_at'])); ?>
                    </span>
                </div>
                <p class="mb-0"><?php echo htmlspecialchars($note['note_text']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="<?php echo BASE_PATH; ?>/buyer/invoices.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Invoices
            </a>
            
            <?php if (in_array($invoice['status'], ['submitted', 'under_review'])): ?>
            <button class="btn btn-success" onclick="approveInvoice(<?php echo $invoice['id']; ?>)">
                <i class="bi bi-check-circle me-2"></i> Approve & Sync Invoice
            </button>
            <button class="btn btn-warning" onclick="requestCorrection(<?php echo $invoice['id']; ?>)">
                <i class="bi bi-exclamation-circle me-2"></i> Reject Invoice
            </button>
            <?php endif; ?>
            
            <?php if (in_array($invoice['status'], ['approved']) && !$invoice['netsuite_bill_id']): ?>
            <button class="btn btn-primary" onclick="syncToNetSuite(<?php echo $invoice['id']; ?>)">
                <i class="bi bi-cloud-arrow-up me-2"></i> Sync to NetSuite
            </button>
            <?php endif; ?>
            
            <?php if ($invoice['netsuite_bill_id']): ?>
            <a href="<?php echo getNetSuiteLink($invoice); ?>" target="_blank" class="btn btn-info">
                <i class="bi bi-box-arrow-up-right me-2"></i> View in NetSuite
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 11"></div>

<script>
const BASE_PATH = '<?php echo BASE_PATH; ?>';
const INVOICE_ID = <?php echo $invoice['id']; ?>;

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        console.log(message);
        return;
    }
    
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-${type} text-white">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${escapeHtml(message)}
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
}

function approveInvoice(invoiceId) {
    const departmentId = document.getElementById('department_id').value;
    const locationId = document.getElementById('location_id').value;
    const postingPeriodId = document.getElementById('posting_period_id').value;
    
    if (!departmentId || !locationId || !postingPeriodId) {
        showToast('Please select Department, Location, and Posting Period before approving', 'warning');
        return;
    }
    
    if (confirm('Are you sure you want to approve and sync this invoice to NetSuite?')) {
        showToast('Syncing invoice to NetSuite...', 'info');
        performNetSuiteSync(invoiceId, departmentId, locationId, postingPeriodId)
        .then(() => {
            showToast('Invoice synced to NetSuite, approving in portal...', 'success');
            return fetch(`${BASE_PATH}/api/invoices.php?action=approve&id=${invoiceId}`, {
                method: 'POST'
            })
            .then(response => response.json());
        })
        .then(data => {
            if (data.success) {
                showToast('Invoice approved successfully', 'success');
                setTimeout(() => {
                    window.location.href = `${BASE_PATH}/buyer/invoices.php`;
                }, 1500);
            } else {
                showToast('Error: ' + (data.error || 'Failed to approve invoice'), 'error');
                throw new Error(data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast(error.message || 'Failed to complete approval and sync', 'error');
        });
    }
}

function requestCorrection(invoiceId) {
    const reason = prompt('Enter reason for rejecting invoice:');
    if (reason) {
        fetch(`${BASE_PATH}/api/invoices.php?action=request_correction&id=${invoiceId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ reason: reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Correction request sent', 'success');
                setTimeout(() => {
                    window.location.href = `${BASE_PATH}/buyer/invoices.php`;
                }, 1500);
            } else {
                showToast('Error: ' + (data.error || 'Failed to send correction request'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast(error.message || 'Failed to send correction request', 'error');
        });
    }
}

function syncToNetSuite(invoiceId) {
    const departmentId = document.getElementById('department_id').value;
    const locationId = document.getElementById('location_id').value;
    const postingPeriodId = document.getElementById('posting_period_id').value;
    
    if (!departmentId || !locationId || !postingPeriodId) {
        showToast('Please select Department, Location, and Posting Period before syncing', 'warning');
        return;
    }
    
    if (confirm('Are you sure you want to sync this invoice to NetSuite?')) {
        return performNetSuiteSync(invoiceId, departmentId, locationId, postingPeriodId)
            .then(() => {
                showToast('Invoice synced to NetSuite successfully', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            })
            .catch(error => {
                console.error('Error:', error);
                showToast(error.message || 'Failed to sync invoice to NetSuite', 'error');
            });
    }
}

function performNetSuiteSync(invoiceId, departmentId, locationId, postingPeriodId) {
    return fetch(`${BASE_PATH}/api/invoices.php?action=sync_to_netsuite&id=${invoiceId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            department_id: departmentId,
            location_id: locationId,
            posting_period_id: postingPeriodId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Failed to sync invoice');
        }
        return data;
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
