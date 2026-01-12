<?php
/**
 * Buyer Dashboard
 * View and manage all purchase orders
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

use LagunaPartners\Auth;
use LagunaPartners\Database;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Define base path for redirects
define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

session_start();

// Check authentication and user type
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

// Get filter options
$vendors = $db->fetchAll("SELECT DISTINCT id, company_name FROM accounts WHERE type = 'vendor' ORDER BY company_name");
$buyers = $db->fetchAll("SELECT DISTINCT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE type = 'user' AND role IN ('buyer', 'accounting') ORDER BY name");

// Get count of POs with vendor updates
$updateCount = $db->fetchOne("SELECT COUNT(*) as count FROM purchase_orders WHERE has_vendor_updates = 1");
$vendorUpdateCount = $updateCount['count'] ?? 0;

$pageTitle = 'Buyer Dashboard - Purchase Orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" href="<?= BASE_PATH ?>/buyer/dashboard.php">
                        <i class="bi bi-clipboard-check"></i> Purchase Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_PATH ?>/buyer/invoices.php">
                        <i class="bi bi-receipt"></i> Invoices
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Purchase Orders</h2>
            <p class="text-muted">View and manage all purchase orders</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($vendorUpdateCount > 0): ?>
            <div class="alert alert-warning mb-0">
                <i class="bi bi-exclamation-triangle"></i>
                <strong><?php echo $vendorUpdateCount; ?></strong> PO(s) with vendor updates
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All Statuses</option>
                        <option value="B">Pending Received</option>
                        <option value="E">Partially Received</option>
                        <option value="F">Pending Bill</option>
                        <option value="H">Fully Billed</option>
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
                    <label class="form-label">Assigned Buyer</label>
                    <select class="form-select" id="filterBuyer">
                        <option value="">All Buyers</option>
                        <?php foreach ($buyers as $buyer): ?>
                        <option value="<?php echo $buyer['id']; ?>"><?php echo htmlspecialchars($buyer['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Vendor Updates</label>
                    <select class="form-select" id="filterUpdates">
                        <option value="">All</option>
                        <option value="true">Has Updates</option>
                        <option value="false">No Updates</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by PO# or vendor name...">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button class="btn btn-primary me-2" id="applyFiltersBtn">Apply Filters</button>
                    <button class="btn btn-outline-secondary" id="clearFiltersBtn">Clear Filters</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Orders Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="poTable">
                    <thead>
                        <tr>
                            <th>PO#</th>
                            <th>Vendor</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Vessel Onboard Date</th>
                            <th>US Delivery Date</th>
                            <th>VesselShip Date</th>
                            <th>Items</th>
                            <th>Buyer</th>
                            <th>Updates</th>
                        </tr>
                    </thead>
                    <tbody id="poTableBody">
                        <tr>
                            <td colspan="11" class="text-center">
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

<!-- PO Details Modal -->
<div class="modal fade" id="poModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purchase Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="poModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_PATH = '<?= BASE_PATH ?>';
let poModal;

document.addEventListener('DOMContentLoaded', function() {
    poModal = new bootstrap.Modal(document.getElementById('poModal'));
    
    loadPurchaseOrders();
    
    // Filter functionality
    document.getElementById('applyFiltersBtn').addEventListener('click', loadPurchaseOrders);
    document.getElementById('clearFiltersBtn').addEventListener('click', function() {
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterVendor').value = '';
        document.getElementById('filterBuyer').value = '';
        document.getElementById('filterUpdates').value = '';
        document.getElementById('searchInput').value = '';
        loadPurchaseOrders();
    });
    
    // Search on enter
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            loadPurchaseOrders();
        }
    });
});

function loadPurchaseOrders() {
    const tbody = document.getElementById('poTableBody');
    tbody.innerHTML = '<tr><td colspan="11" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';
    
    const params = new URLSearchParams({
        status: document.getElementById('filterStatus').value,
        vendor_id: document.getElementById('filterVendor').value,
        buyer_id: document.getElementById('filterBuyer').value,
        has_updates: document.getElementById('filterUpdates').value,
        search: document.getElementById('searchInput').value
    });
    
    fetch(`${BASE_PATH}/api/purchase-orders.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPurchaseOrders(data.data);
            } else {
                showToast('Error loading purchase orders: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load purchase orders', 'error');
        });
}

function displayPurchaseOrders(pos) {
    const tbody = document.getElementById('poTableBody');
    
    if (pos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted">No purchase orders found</td></tr>';
        return;
    }
    
    tbody.innerHTML = pos.map(po => {
        const statusMap = {
            'A': { text: 'Pending Approval', class: 'warning' },
            'B': { text: 'Pending Received', class: 'warning' },
            'E': { text: 'Partially Received', class: 'info' },
            'F': { text: 'Pending Bill', class: 'primary' },
            'H': { text: 'Fully Billed', class: 'success' }
        };
        
        const status = statusMap[po.status] || { text: po.status, class: 'secondary' };
        const hasUpdates = parseInt(po.has_vendor_updates) === 1;
        const isRejected = po.rejection_reason;
        const rowStyle = isRejected ? 'background-color: #ffe6e6;' : '';
        const rejectionTooltip = isRejected ? `title="${escapeHtml(po.rejection_reason)}"` : '';
        
        return `
            <tr style="cursor: pointer; ${rowStyle}" onclick="window.location.href='${BASE_PATH}/buyer/purchase-order.php?id=${po.id}'">
                <td><strong>${escapeHtml(po.tran_id)}</strong><br>${isRejected ? '<small class="text-danger"><strong>Vendor Rejected</strong></small>' : ''}</td>
                <td>${escapeHtml(po.company_name || 'N/A')}</td>
                <td>$${parseFloat(po.total_amount || 0).toFixed(2)}</td>
                <td><span class="badge bg-${status.class}">${status.text}</span>${isRejected ? `<br><span class="badge bg-danger mt-1" ${rejectionTooltip}>Rejected</span>` : ''}</td>
                <td>${formatDate(po.created_date)}</td>
                <td>${formatDate(po.port_date)}</td>
                <td>${formatDate(po.estimated_delivery_date)}</td>
                <td>${formatDate(po.ship_date)}</td>
                <td><span class="badge bg-secondary">${po.item_count || 0}</span></td>
                <td>${escapeHtml(po.buyer_name || 'Unassigned')}</td>
                <td>${hasUpdates ? '<span class="badge bg-danger">Has Updates</span>' : ''}</td>
            </tr>
        `;
    }).join('');
}

function viewPO(poId) {
    const modalBody = document.getElementById('poModalBody');
    modalBody.innerHTML = '<div class="text-center"><div class="spinner-border text-primary"></div></div>';
    
    poModal.show();
    
    fetch(`${BASE_PATH}/api/purchase-orders.php?id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPODetails(data.data);
            } else {
                modalBody.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="alert alert-danger">Failed to load purchase order details</div>';
        });
}

function displayPODetails(po) {
    const statusMap = {
        'B': 'Pending Received',
        'E': 'Partially Received',
        'F': 'Pending Bill',
        'H': 'Fully Billed'
    };
    
    const hasUpdates = parseInt(po.has_vendor_updates) === 1;
    
    let html = `
        <div class="row mb-4">
            <div class="col-md-6">
                <h4>PO# ${escapeHtml(po.tran_id)}</h4>
                <p class="text-muted mb-1"><strong>Vendor:</strong> ${escapeHtml(po.company_name || 'N/A')}</p>
                <p class="text-muted mb-1"><strong>Status:</strong> ${statusMap[po.status] || po.status}</p>
                <p class="text-muted mb-1"><strong>Created:</strong> ${formatDate(po.created_date)}</p>
               ${po.vessel_identifier ? `
                <p><a href="https://www.myshiptracking.com/?mmsi=${encodeURIComponent(po.vessel_identifier || '')}" target="_blank" rel="noopener noreferrer">Track Vessel</a></p>
                ` : ''}
            </div>
            <div class="col-md-6 text-end">
                <h4>$${parseFloat(po.total_amount || 0).toFixed(2)}</h4>
                ${hasUpdates ? '<div class="alert alert-warning mt-2"><i class="bi bi-exclamation-triangle"></i> Vendor has made updates</div>' : ''}
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label"><strong>Vessel Name</strong></label>
                <input type="text" class="form-control" id="editVesselName" value="${po.vessel_name || ''}">
            </div>
            <div class="col-md-4">
                <label class="form-label"><strong>Vessel Identifier</strong></label>
                <input type="text" class="form-control" id="editVesselIdentifier" value="${po.vessel_identifier || ''}">
            </div>
            <div class="col-md-4">
                <label class="form-label"><strong>Expected Factory Date</strong></label>
                <input type="date" class="form-control" id="editExpectedFactoryDate" value="${po.expected_factory_date || ''}">
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label"><strong>Vessel Onboard Date Date</strong></label>
                <input type="date" class="form-control" id="editPortDate" value="${po.port_date || ''}">
            </div>
            <div class="col-md-4">
                <label class="form-label"><strong>US Delivery Date</strong></label>
                <input type="date" class="form-control" id="editEstDelivery" value="${po.estimated_delivery_date || ''}">
            </div>
            <div class="col-md-4">
                <label class="form-label"><strong>Vessel Ship Date</strong></label>
                <input type="date" class="form-control" id="editShipDate" value="${po.ship_date || ''}">
            </div>
        </div>
        
        <div class="mb-4">
            <label class="form-label"><strong>Memo</strong></label>
            <textarea class="form-control" id="editMemo" rows="2">${escapeHtml(po.memo || '')}</textarea>
        </div>
        
        <div class="mb-3">
            <button class="btn btn-primary" onclick="savePOChanges(${po.id})">
                <i class="bi bi-save"></i> Save Changes
            </button>
            ${hasUpdates ? `<button class="btn btn-success ms-2" onclick="approveVendorChanges(${po.id})">
                <i class="bi bi-check-circle"></i> Approve & Sync to NetSuite
            </button>` : ''}
        </div>
        
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#itemsTab">Items (${po.items.length})</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#commentsTab">Comments (${po.comments.length})</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#documentsTab">Documents (${po.documents.length})</a>
            </li>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="itemsTab">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Original Qty</th>
                                <th>Vendor/Shipped Qty</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${po.items.map(item => `
                                <tr>
                                    <td>${escapeHtml(item.item_name)}</td>
                                    <td>${item.quantity || 0}</td>
                                    <td>${item.vendor_quantity || item.quantity || 0}</td>
                                    <td>$${parseFloat(item.amount || 0).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="tab-pane fade" id="commentsTab">
                <div class="mb-3">
                    <textarea class="form-control" id="newComment" rows="2" placeholder="Add a comment..."></textarea>
                    <button class="btn btn-sm btn-primary mt-2" onclick="addComment(${po.id})">Add Comment</button>
                </div>
                <div id="commentsList">
                    ${po.comments.map(comment => `
                        <div class="card mb-2">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between">
                                    <strong>${escapeHtml(comment.user_name || comment.user_email)}</strong>
                                    <small class="text-muted">${formatDateTime(comment.created_at)}</small>
                                </div>
                                <p class="mb-0 mt-1">${escapeHtml(comment.comment)}</p>
                            </div>
                        </div>
                    `).join('') || '<p class="text-muted">No comments yet</p>'}
                </div>
            </div>
            
            <div class="tab-pane fade" id="documentsTab">
                <div id="documentsList">
                    ${po.documents.map(doc => `
                        <div class="card mb-2">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <i class="bi bi-file-earmark"></i>
                                        <strong>${escapeHtml(doc.original_filename)}</strong>
                                        ${doc.document_type ? `<span class="badge bg-info ms-2">${escapeHtml(doc.document_type)}</span>` : ''}
                                        <small class="text-muted ms-2">(${formatFileSize(doc.file_size)})</small>
                                        ${doc.comment ? `<p class="mb-0 mt-1 text-muted small">${escapeHtml(doc.comment)}</p>` : ''}
                                        <small class="text-muted d-block">${formatDateTime(doc.created_at)}</small>
                                    </div>
                                    <div>
                                        <a href="${BASE_PATH}/api/download.php?id=${doc.id}" class="btn btn-sm btn-outline-primary" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('') || '<p class="text-muted">No documents uploaded</p>'}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('poModalBody').innerHTML = html;
}

function savePOChanges(poId) {
    const data = {
        id: poId,
        vessel_name: document.getElementById('editVesselName').value,
        vessel_identifier: document.getElementById('editVesselIdentifier').value,
        expected_factory_date: document.getElementById('editExpectedFactoryDate').value,
        port_date: document.getElementById('editPortDate').value,
        estimated_delivery_date: document.getElementById('editEstDelivery').value,
        ship_date: document.getElementById('editShipDate').value,
        memo: document.getElementById('editMemo').value
    };
    
    fetch(`${BASE_PATH}/api/purchase-orders.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            loadPurchaseOrders();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save changes', 'error');
    });
}

function approveVendorChanges(poId) {
    if (!confirm('This will sync the changes to NetSuite and notify the vendor. Continue?')) {
        return;
    }
    
    fetch(`${BASE_PATH}/api/purchase-orders.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'approve_changes',
            po_id: poId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            poModal.hide();
            loadPurchaseOrders();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to approve changes', 'error');
    });
}

function addComment(poId) {
    const comment = document.getElementById('newComment').value.trim();
    
    if (!comment) {
        showToast('Please enter a comment', 'error');
        return;
    }
    
    fetch(`${BASE_PATH}/api/purchase-orders.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'add_comment',
            po_id: poId,
            comment: comment
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('newComment').value = '';
            viewPO(poId); // Reload PO details
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to add comment', 'error');
    });
}

function formatDate(dateStr) {
    if (!dateStr || dateStr === '0000-00-00' || dateStr === '1970-01-01') return '-';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleDateString();
}

function formatDateTime(dateStr) {
    if (!dateStr || dateStr === '0000-00-00' || dateStr === '1970-01-01') return '-';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleString();
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '9999';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>