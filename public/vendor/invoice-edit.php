<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Auth;
use LagunaPartners\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

Auth::requireAuth(['vendor']);

$db = Database::getInstance();
$user = Auth::user();

$invoice_id = intval($_GET['id'] ?? 0);
if (!$invoice_id) {
    header('Location: ' . BASE_PATH . '/vendor/invoices.php');
    exit;
}

$invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ? AND vendor_id = ?", [$invoice_id, $user['account_id']]);
if (!$invoice || !in_array($invoice['status'], ['draft', 'rejected'])) {
    http_response_code(403);
    header('Location: ' . BASE_PATH . '/vendor/invoices.php');
    exit;
}

$line_items = $db->fetchAll("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY line_number", [$invoice_id]);

$pageTitle = 'Edit Invoice - ' . htmlspecialchars($invoice['invoice_number']);
include __DIR__ . '/../includes/header.php';
?>

<style>
    .form-section {
        background: white;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .form-section h4 {
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 20px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 12px;
    }

    .form-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border-radius: 6px;
        border: 1px solid #ddd;
        padding: 10px 12px;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }

    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 30px;
    }

    .form-actions .btn {
        min-width: 140px;
    }

    .breadcrumb-nav {
        margin-bottom: 25px;
    }

    .line-items-table {
        margin: 20px 0;
    }
</style>

<div class="container" style="margin-top: 30px; margin-bottom: 30px; max-width: 1000px;">
    <div class="breadcrumb-nav">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/vendor/invoices.php">Invoices</a></li>
                <li class="breadcrumb-item"><a href="<?php echo BASE_PATH; ?>/vendor/invoice-view.php?id=<?php echo $invoice_id; ?>">Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>

    <h1 class="h2 mb-4" style="color: var(--primary); font-weight: 700;">
        <i class="fas fa-edit me-2"></i> Edit Invoice
    </h1>

    <form id="form-edit-invoice">
        <!-- Basic Information Section -->
        <div class="form-section">
            <h4><i class="fas fa-info-circle me-2"></i> Invoice Information</h4>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Number (Read-only)</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($invoice['invoice_number']); ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Date *</label>
                    <input type="date" class="form-control" id="invoice-date" value="<?php echo $invoice['invoice_date']; ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Invoice Type *</label>
                    <select class="form-select" id="invoice-type" required>
                        <option value="down_payment" <?php echo $invoice['invoice_type'] === 'down_payment' ? 'selected' : ''; ?>>Down Payment</option>
                        <option value="regular" <?php echo $invoice['invoice_type'] === 'regular' ? 'selected' : ''; ?>>Regular Invoice</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" id="due-date-label">Due Date</label>
                    <input type="date" class="form-control" id="invoice-due-date" value="<?php echo $invoice['due_date'] ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Total Amount *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="invoice-amount" step="0.01" value="<?php echo $invoice['amount_total']; ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Line Items -->
        <div class="form-section">
            <h4><i class="fas fa-list-check me-2"></i> Current Line Items</h4>
            <?php if (!empty($line_items)): ?>
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
                            <?php foreach ($line_items as $item): ?>
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
            <?php else: ?>
            <p class="text-muted">No line items found.</p>
            <?php endif; ?>
        </div>

        <!-- Notes Section -->
        <div class="form-section">
            <h4><i class="fas fa-sticky-note me-2"></i> Notes</h4>
            <div class="mb-3">
                <label class="form-label">Description/Notes</label>
                <textarea class="form-control" id="invoice-description" rows="4" placeholder="Add any additional notes..."><?php echo htmlspecialchars($invoice['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="form-actions">
            <a href="<?php echo BASE_PATH; ?>/vendor/invoice-view.php?id=<?php echo $invoice_id; ?>" class="btn btn-secondary">
                <i class="fas fa-times me-2"></i> Cancel
            </a>
            <button type="button" class="btn btn-outline-primary" onclick="updateInvoice('draft')">
                <i class="fas fa-save me-2"></i> Save Changes
            </button>
            <button type="button" class="btn btn-primary" onclick="updateInvoice('submit')">
                <i class="fas fa-paper-plane me-2"></i> Submit for Review
            </button>
        </div>
    </form>
</div>

<div class="position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 11"></div>

<script>
const BASE_PATH = '<?php echo BASE_PATH; ?>';
const INVOICE_ID = <?php echo $invoice_id; ?>;

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

function updateInvoice(action) {
    const invoiceDate = document.getElementById('invoice-date').value;
    const invoiceType = document.getElementById('invoice-type').value;
    const dueDate = document.getElementById('invoice-due-date').value;
    const invoiceAmountValue = document.getElementById('invoice-amount').value;
    const invoiceTotal = parseFloat(invoiceAmountValue);
    const description = document.getElementById('invoice-description').value;

    if (!invoiceDate || !invoiceType) {
        showToast('Please complete all required invoice fields.', 'warning');
        return;
    }

    if (!Number.isFinite(invoiceTotal) || invoiceTotal <= 0) {
        showToast('Please enter a valid invoice total amount.', 'warning');
        return;
    }

    const payload = {
        invoice_date: invoiceDate,
        due_date: dueDate || null,
        amount_total: invoiceTotal,
        invoice_type: invoiceType,
        description: description
    };

    showToast('Updating invoice...', 'info');

    fetch(`${BASE_PATH}/api/invoices.php?action=update&id=${INVOICE_ID}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Invoice updated successfully', 'success');
            
            if (action === 'submit') {
                setTimeout(() => {
                    fetch(`${BASE_PATH}/api/invoices.php?action=submit&id=${INVOICE_ID}`, {
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
                }, 1000);
            } else {
                setTimeout(() => {
                    window.location.href = `${BASE_PATH}/vendor/invoice-view.php?id=${INVOICE_ID}`;
                }, 1500);
            }
        } else {
            showToast('Error: ' + (data.error || 'Failed to update invoice'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast(error.message || 'Failed to update invoice', 'error');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const invoiceDateInput = document.getElementById('invoice-date');
    if (invoiceDateInput) {
        invoiceDateInput.addEventListener('change', updateDueDate);
    }
});

function updateDueDate() {
    const invoiceDateInput = document.getElementById('invoice-date');
    const dueDateInput = document.getElementById('invoice-due-date');
    const dueDateLabel = document.getElementById('due-date-label');
    
    if (!invoiceDateInput.value) {
        dueDateInput.value = '';
        dueDateLabel.textContent = 'Due Date ';
        return;
    }
    
    fetch(`${BASE_PATH}/api/vendor-profile.php?action=get_term_info`)
        .then(res => res.json())
        .then(data => {
            let dueDays = 30;
            let termLabel = '';
            
            if (data.success && data.data) {
                if (data.data.invoice_due_days !== null) {
                    dueDays = data.data.invoice_due_days;
                }
                if (data.data.term) {
                    termLabel = ` (${data.data.term})`;
                }
            }
            
            dueDateLabel.textContent = `Due Date ${termLabel}`;
            
            if (dueDays === -1) {
                dueDateInput.value = '';
                return;
            }
            
            const invoiceDate = new Date(invoiceDateInput.value);
            const dueDate = new Date(invoiceDate);
            dueDate.setDate(dueDate.getDate() + dueDays);
            
            const year = dueDate.getFullYear();
            const month = String(dueDate.getMonth() + 1).padStart(2, '0');
            const day = String(dueDate.getDate()).padStart(2, '0');
            
            dueDateInput.value = `${year}-${month}-${day}`;
        })
        .catch(err => {
            console.error('Error fetching term info:', err);
            dueDateLabel.textContent = 'Due Date (No Terms)';
            
            const invoiceDate = new Date(invoiceDateInput.value);
            const dueDate = new Date(invoiceDate);
            dueDate.setDate(dueDate.getDate() + 30);
            
            const year = dueDate.getFullYear();
            const month = String(dueDate.getMonth() + 1).padStart(2, '0');
            const day = String(dueDate.getDate()).padStart(2, '0');
            
            dueDateInput.value = `${year}-${month}-${day}`;
        });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
