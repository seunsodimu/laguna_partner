<?php
/**
 * Admin Purchase Orders Management
 * View and manage purchase orders
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
if ($user['type'] !== 'user' || !in_array($user['role'], ['buyer', 'accounting', 'admin'])) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$db = Database::getInstance();

$vendors = $db->fetchAll("SELECT DISTINCT vendor_name FROM purchase_orders ORDER BY vendor_name");
$statuses = $db->fetchAll("SELECT DISTINCT status FROM purchase_orders ORDER BY status");

$statusMap = [
    'A' => 'Pending Approval',
    'B' => 'Pending Received',
    'E' => 'Partially Received',
    'F' => 'Pending Billing/Partially Received',
    'H' => 'Pending Billing'
];

$pageTitle = 'Purchase Orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Purchase Orders</h2>
            <p class="text-muted">View and manage purchase orders</p>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search (PO # or Vendor)</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Search...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vendor</label>
                    <select class="form-select" id="filterVendor">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $vendor): ?>
                        <option value="<?php echo htmlspecialchars($vendor['vendor_name']); ?>"><?php echo htmlspecialchars($vendor['vendor_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo htmlspecialchars($status['status']); ?>"><?php echo htmlspecialchars($statusMap[$status['status']] ?? $status['status']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" id="filterDateFrom">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" id="filterDateTo">
                </div>
                <div class="col-md-3"></div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-secondary w-100" onclick="resetFilters()">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                Purchase Orders
                <span class="badge bg-secondary ms-2" id="poCount">0 records</span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>PO Number</th>
                        <th>Vendor</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="posTable">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <nav id="paginationContainer"></nav>
</div>

<script>
const BASE_PATH = '<?php echo BASE_PATH; ?>';
let currentPage = 1;
const posPerPage = 50;
const statusMap = {
    'A': 'Pending Approval',
    'B': 'Pending Received',
    'E': 'Partially Received',
    'F': 'Pending Billing/Partially Received',
    'H': 'Pending Billing'
};

document.addEventListener('DOMContentLoaded', function() {
    loadPurchaseOrders();
});

function loadPurchaseOrders() {
    const params = new URLSearchParams({
        action: 'list',
        page: currentPage,
        limit: posPerPage
    });
    
    fetch(`${BASE_PATH}/api/admin-purchase-orders.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPOs(data.data);
                displayPagination(data.pagination);
                document.getElementById('poCount').textContent = data.pagination.total + ' records';
            } else {
                showError('Failed to load purchase orders: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to load purchase orders');
        });
}

function applyFilters() {
    const search = document.getElementById('filterSearch').value.trim();
    const vendor = document.getElementById('filterVendor').value;
    const status = document.getElementById('filterStatus').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    
    const params = new URLSearchParams({
        action: 'search',
        page: 1,
        limit: posPerPage
    });
    
    if (search) params.append('search', search);
    if (vendor) params.append('vendor', vendor);
    if (status) params.append('status', status);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    
    fetch(`${BASE_PATH}/api/admin-purchase-orders.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPOs(data.data);
                displayPagination(data.pagination);
                document.getElementById('poCount').textContent = data.pagination.total + ' records';
                currentPage = 1;
            } else {
                showError('Failed to search: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to search');
        });
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterVendor').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    currentPage = 1;
    loadPurchaseOrders();
}

function displayPOs(pos) {
    const table = document.getElementById('posTable');
    
    if (pos.length === 0) {
        table.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No purchase orders found</td></tr>';
        return;
    }
    
    table.innerHTML = pos.map(po => `
        <tr>
            <td>
                <strong>${htmlEscape(po.tran_id)}</strong>
            </td>
            <td>
                ${htmlEscape(po.vendor_name)}
            </td>
            <td>
                ${po.currency} ${formatCurrency(po.total_amount)}
            </td>
            <td>
                <span class="badge bg-${getStatusBadgeColor(po.status)}">${htmlEscape(po.status_description || po.status)}</span>
            </td>
            <td>
                <small class="text-muted">${po.created_date ? new Date(po.created_date).toLocaleDateString() : 'N/A'}</small>
            </td>
            <td>
                <small class="text-muted">${po.due_date ? new Date(po.due_date).toLocaleDateString() : 'N/A'}</small>
            </td>
            <td>
                <a href="${BASE_PATH}/buyer/purchase-order.php?id=${po.id}" class="btn btn-sm btn-info" title="View">
                    <i class="bi bi-eye"></i>
                </a>
            </td>
        </tr>
    `).join('');
}

function displayPagination(pagination) {
    const container = document.getElementById('paginationContainer');
    
    if (pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<ul class="pagination justify-content-center">';
    
    if (currentPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(1); return false;">First</a></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;">Previous</a></li>`;
    }
    
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(pagination.pages, currentPage + 2); i++) {
        if (i === currentPage) {
            html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a></li>`;
        }
    }
    
    if (currentPage < pagination.pages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;">Next</a></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${pagination.pages}); return false;">Last</a></li>`;
    }
    
    html += '</ul>';
    container.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    window.scrollTo(0, 0);
    
    const search = document.getElementById('filterSearch').value.trim();
    const vendor = document.getElementById('filterVendor').value;
    const status = document.getElementById('filterStatus').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    
    if (search || vendor || status || dateFrom || dateTo) {
        applyFilters();
    } else {
        loadPurchaseOrders();
    }
}

function getStatusBadgeColor(status) {
    const colors = {
        'A': 'warning',
        'B': 'info',
        'E': 'primary',
        'F': 'info',
        'H': 'warning'
    };
    return colors[status] || 'secondary';
}

function formatCurrency(amount) {
    return parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function showError(message) {
    document.getElementById('posTable').innerHTML = `<tr><td colspan="7" class="text-danger text-center py-4">${htmlEscape(message)}</td></tr>`;
}

function htmlEscape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
