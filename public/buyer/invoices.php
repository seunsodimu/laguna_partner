<?php
/**
 * Buyer Invoice Management
 * View and manage invoices from vendors
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

$user = Auth::user();
if ($user['type'] !== 'user' || !in_array($user['role'], ['buyer', 'accounting', 'admin'])) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$db = Database::getInstance();

$vendors = $db->fetchAll("SELECT DISTINCT id, company_name FROM accounts WHERE type = 'vendor' ORDER BY company_name");

$pageTitle = 'Buyer Dashboard - Invoice Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_PATH ?>/buyer/dashboard.php">
                        <i class="bi bi-clipboard-check"></i> Purchase Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?= BASE_PATH ?>/buyer/invoices.php">
                        <i class="bi bi-receipt"></i> Invoices
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Invoice Management</h2>
            <p class="text-muted">View and manage all vendor invoices</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" id="createInvoiceBtn" style="display: none;">
                <i class="bi bi-plus-circle"></i> Create Invoice
            </button>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="under_review">Under Review</option>
                        <option value="approved">Approved</option>
                        <option value="processing">Processing</option>
                        <option value="paid">Paid</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vendor</label>
                    <select class="form-select" id="filterVendor">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $vendor): ?>
                        <option value="<?php echo $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['company_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Invoice Type</label>
                    <select class="form-select" id="filterType">
                        <option value="">All Types</option>
                        <option value="regular">Regular</option>
                        <option value="down_payment">Down Payment</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by invoice number...">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button class="btn btn-primary me-2" id="applyFiltersBtn">Apply Filters</button>
                    <button class="btn btn-outline-secondary" id="clearFiltersBtn">Clear Filters</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="invoiceTable">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Vendor</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceTableBody">
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>



<div class="position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 11"></div>

<script>
const BASE_PATH = '<?= BASE_PATH ?>';

document.addEventListener('DOMContentLoaded', function() {
    loadInvoices();
    
    document.getElementById('applyFiltersBtn').addEventListener('click', loadInvoices);
    document.getElementById('clearFiltersBtn').addEventListener('click', function() {
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterVendor').value = '';
        document.getElementById('filterType').value = '';
        document.getElementById('searchInput').value = '';
        loadInvoices();
    });
    
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            loadInvoices();
        }
    });
});

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

function formatDate(dateString) {
    if (!dateString || dateString === '0000-00-00' || dateString === '1970-01-01') return '-';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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

function loadInvoices() {
    const tbody = document.getElementById('invoiceTableBody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';
    
    const params = new URLSearchParams({
        action: 'list',
        status: document.getElementById('filterStatus').value,
        vendor_id: document.getElementById('filterVendor').value,
        invoice_type: document.getElementById('filterType').value,
        search: document.getElementById('searchInput').value
    });
    
    fetch(`${BASE_PATH}/api/invoices.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayInvoices(data.data);
            } else {
                showToast('Error loading invoices: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load invoices', 'error');
        });
}

function displayInvoices(invoices) {
    const tbody = document.getElementById('invoiceTableBody');
    
    if (invoices.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No invoices found</td></tr>';
        return;
    }
    
    tbody.innerHTML = invoices.map(invoice => {
        const statusMap = {
            'draft': { text: 'Draft', class: 'secondary' },
            'submitted': { text: 'Submitted', class: 'info' },
            'under_review': { text: 'Under Review', class: 'warning' },
            'approved': { text: 'Approved', class: 'success' },
            'processing': { text: 'Processing', class: 'primary' },
            'paid': { text: 'Paid', class: 'success' },
            'rejected': { text: 'Rejected', class: 'danger' }
        };
        
        const typeMap = {
            'regular': 'Regular',
            'down_payment': 'Down Payment'
        };
        
        const status = statusMap[invoice.status] || { text: invoice.status, class: 'secondary' };
        const type = typeMap[invoice.invoice_type] || invoice.invoice_type;
        
        return `
            <tr>
                <td><strong>${escapeHtml(invoice.invoice_number)}</strong></td>
                <td>${escapeHtml(invoice.vendor_name || 'N/A')}</td>
                <td>${formatDate(invoice.invoice_date)}</td>
                <td>${formatDate(invoice.due_date)}</td>
                <td>$${parseFloat(invoice.amount_total || 0).toFixed(2)}</td>
                <td>${type}</td>
                <td><span class="badge bg-${status.class}">${status.text}</span></td>
                <td>
                    ${invoice.note_count > 0 ? `<span class="badge bg-secondary">${invoice.note_count}</span>` : ''}
                    ${invoice.attachment_count > 0 ? `<span class="badge bg-info">${invoice.attachment_count} files</span>` : ''}
                </td>
                <td>
                    <a href="${BASE_PATH}/buyer/invoice.php?id=${invoice.id}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> View
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}



function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
