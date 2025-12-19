<?php
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

$user = Auth::user();
if ($user['type'] !== 'vendor') {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$db = Database::getInstance();
$invoice_id = intval($_GET['id'] ?? 0);

if (!$invoice_id) {
    header('Location: ' . BASE_PATH . '/vendor/invoices.php');
    exit;
}

$invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);
if (!$invoice || $invoice['vendor_id'] != $user['account_id']) {
    header('Location: ' . BASE_PATH . '/vendor/invoices.php');
    exit;
}

$invoice['line_items'] = $db->fetchAll("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY line_number", [$invoice_id]);
$invoice['attachments'] = $db->fetchAll("SELECT * FROM invoice_attachments WHERE invoice_id = ?", [$invoice_id]);
$invoice['notes'] = $db->fetchAll("SELECT * FROM invoice_notes WHERE invoice_id = ? ORDER BY created_at DESC", [$invoice_id]);

$grouped_items = [];
foreach ($invoice['line_items'] as $item) {
    if ($item['reference'] && is_numeric($item['reference'])) {
        $po_id = intval($item['reference']);
        if (!isset($grouped_items[$po_id])) {
            $po = $db->fetchOne("SELECT id, tran_id FROM purchase_orders WHERE id = ?", [$po_id]);
            $grouped_items[$po_id] = [
                'po_id' => $po_id,
                'po_number' => $po['tran_id'] ?? 'PO #' . $po_id,
                'items' => []
            ];
        }
        $grouped_items[$po_id]['items'][] = $item;
    } else {
        if (!isset($grouped_items[0])) {
            $grouped_items[0] = [
                'po_id' => 0,
                'po_number' => 'Manual Line Items',
                'items' => []
            ];
        }
        $grouped_items[0]['items'][] = $item;
    }
}

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
        display: flex;
        justify-content: space-between;
        align-items: center;
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
    
    .po-group {
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        margin: 15px 20px;
        overflow: hidden;
    }
    
    .po-group-header {
        background: #f5f5f5;
        padding: 12px 15px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .po-group-header:hover {
        background: #efefef;
    }
    
    .po-chevron {
        display: inline-block;
        transition: transform 0.3s ease;
    }
    
    .po-chevron.collapsed {
        transform: rotate(-90deg);
    }
    
    .po-group-items {
        padding: 0;
    }
    
    .po-group-items table {
        margin: 0;
        width: 100%;
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
        <div>
            <h1>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
            <p><?php echo htmlspecialchars($invoice['vendor_name'] ?? 'N/A'); ?></p>
        </div>
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
        
        <!-- Line Items Grouped by PO -->
        <?php if (!empty($grouped_items)): ?>
        <div class="section-title">
            <i class="bi bi-list-check me-2"></i> Line Items
        </div>
        <div>
            <?php foreach ($grouped_items as $group): ?>
            <div class="po-group">
                <div class="po-group-header" onclick="togglePOGroup(this)">
                    <span><?php echo htmlspecialchars($group['po_number']); ?> (<?php echo count($group['items']); ?> item<?php echo count($group['items']) !== 1 ? 's' : ''; ?>)</span>
                    <span class="po-chevron">▶</span>
                </div>
                <div class="po-group-items">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Quantity</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group['items'] as $item): ?>
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
            </div>
            <?php endforeach; ?>
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
            <a href="<?php echo BASE_PATH; ?>/vendor/invoices.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Invoices
            </a>
            
            <?php if (in_array($invoice['status'], ['draft', 'rejected'])): ?>
            <a href="<?php echo BASE_PATH; ?>/vendor/invoice-edit.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary">
                <i class="bi bi-pencil me-2"></i> Edit Invoice
            </a>
            <button class="btn btn-success" onclick="submitInvoice(<?php echo $invoice['id']; ?>)">
                <i class="bi bi-check-circle me-2"></i> Submit for Review
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 11"></div>

<script>
const BASE_PATH = '<?php echo BASE_PATH; ?>';
const INVOICE_ID = <?php echo $invoice['id']; ?>;

function togglePOGroup(element) {
    const itemsDiv = element.nextElementSibling;
    const chevron = element.querySelector('.po-chevron');
    
    if (itemsDiv.style.display === 'none') {
        itemsDiv.style.display = 'block';
        chevron.classList.remove('collapsed');
    } else {
        itemsDiv.style.display = 'none';
        chevron.classList.add('collapsed');
    }
}

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

function submitInvoice(invoiceId) {
    if (confirm('Are you sure you want to submit this invoice for review?')) {
        showToast('Submitting invoice...', 'info');
        fetch(`${BASE_PATH}/api/invoices.php?action=submit&id=${invoiceId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Invoice submitted successfully', 'success');
                setTimeout(() => {
                    window.location.href = `${BASE_PATH}/vendor/invoices.php`;
                }, 1500);
            } else {
                showToast('Error: ' + (data.error || 'Failed to submit invoice'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast(error.message || 'Failed to submit invoice', 'error');
        });
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
