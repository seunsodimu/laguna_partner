<?php
/**
 * Vendor Dashboard
 * View and manage purchase orders
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Auth;
use LagunaPartners\Database;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Define base path for redirects
define('BASE_PATH', getenv('APP_BASE_PATH') ?: '/laguna_partner');

Auth::requireAuth(['vendor']);

$db = Database::getInstance();
$user = Auth::user();

// Get user's accounts
$auth = new Auth();
$accounts = $auth->getUserAccounts($user['id']);

// Get active account
$activeAccountId = $_SESSION['active_account_id'] ?? ($accounts[0]['id'] ?? null);

// Get purchase orders for active account
$purchaseOrders = [];
if ($activeAccountId) {
    $purchaseOrders = $db->fetchAll(
        "SELECT po.*, 
                (SELECT COUNT(*) FROM po_items WHERE po_id = po.id) as item_count
         FROM purchase_orders po
         WHERE po.vendor_id = ?
         ORDER BY po.created_date DESC",
        [$activeAccountId]
    );
}

$pageTitle = 'Purchase Orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="bi bi-file-earmark-text"></i> ::Purchase Orders</h2>
        </div>
        <div class="col-md-6 text-end">
            <?php if (count($accounts) > 1): ?>
                <div class="dropdown d-inline-block">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-building"></i> 
                        <?php
                        $activeAccount = array_filter($accounts, fn($a) => $a['id'] == $activeAccountId);
                        echo htmlspecialchars(reset($activeAccount)['company_name'] ?? 'Select Account');
                        ?>
                    </button>
                    <ul class="dropdown-menu">
                        <?php foreach ($accounts as $account): ?>
                            <li>
                                <a class="dropdown-item <?= $account['id'] == $activeAccountId ? 'active' : '' ?>" 
                                   href="?switch_account=<?= $account['id'] ?>">
                                    <?= htmlspecialchars($account['company_name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary h-100">
                <div class="card-body text-center">
                    <i class="bi bi-file-earmark-text" style="font-size: 2rem; color: #0d6efd;"></i>
                    <h5 class="card-title mt-3">Purchase Orders</h5>
                    <p class="card-text text-muted">View and manage purchase orders</p>
                    <a href="<?= BASE_PATH ?>/vendor/dashboard.php" class="btn btn-sm btn-primary">View</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success h-100">
                <div class="card-body text-center">
                    <i class="bi bi-receipt" style="font-size: 2rem; color: #198754;"></i>
                    <h5 class="card-title mt-3">Invoices</h5>
                    <p class="card-text text-muted">Submit and track invoices</p>
                    <a href="<?= BASE_PATH ?>/vendor/invoices.php" class="btn btn-sm btn-success">Manage</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search PO#, Vendor...">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="B">Pending Received</option>
                        <option value="E">Partially Received</option>
                        <option value="F">Pending Billing/Partially Received</option>
                        <option value="H">Pending Billing</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" id="dateFilter" placeholder="Created Date">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100" onclick="clearFilters()">
                        <i class="bi bi-x-circle"></i> Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Purchase Orders Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="poTable">
                    <thead>
                        <tr>
                            <th>PO#</th>
                            <th>Vendor/Company</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Port Date</th>
                            <th>Est. Delivery</th>
                            <th>Ship Date</th>
                            <th>Items</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchaseOrders)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                    <p class="mt-2">No purchase orders found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($purchaseOrders as $po): ?>
                                <tr data-po-id="<?= $po['id'] ?>" 
                                    data-status="<?= htmlspecialchars($po['status']) ?>"
                                    data-date="<?= htmlspecialchars($po['created_date']) ?>">
                                    <td>
                                        <a href="#" class="text-decoration-none" onclick="viewPO(<?= $po['id'] ?>); return false;">
                                            <strong><?= htmlspecialchars($po['tran_id']) ?></strong>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($po['vendor_name']) ?></td>
                                    <td>$<?= number_format($po['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= getStatusColor($po['status']) ?>">
                                            <?= htmlspecialchars($po['status_text']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('m/d/Y', strtotime($po['created_date'])) ?></td>
                                    <td><?= $po['port_date'] ? date('m/d/Y', strtotime($po['port_date'])) : '-' ?></td>
                                    <td><?= $po['estimated_delivery_date'] ? date('m/d/Y', strtotime($po['estimated_delivery_date'])) : '-' ?></td>
                                    <td><?= $po['ship_date'] ? date('m/d/Y', strtotime($po['ship_date'])) : '-' ?></td>
                                    <td><?= $po['item_count'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="viewPO(<?= $po['id'] ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="uploadDoc(<?= $po['id'] ?>)">
                                            <i class="bi bi-upload"></i>
                                        </button>
                                        <a class="btn btn-sm btn-warning mt-1" href="<?= BASE_PATH ?>/vendor/create-invoice.php?po_id=<?= $po['id'] ?>">
                                            <i class="bi bi-receipt"></i> Bill this PO
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
                <h5 class="modal-title">Purchase Order Details:</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="poModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PO Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Reject Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Please provide a reason for rejecting this purchase order. This message will be sent to the buyer.</p>
                <textarea class="form-control" id="rejectionReason" rows="5" placeholder="Enter rejection reason..."></textarea>
                <small class="text-muted d-block mt-2">This field is required</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitRejection()">Reject PO</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_PATH = '<?= BASE_PATH ?>';
let poModal;
let rejectModal;
let currentRejectPoId;

document.addEventListener('DOMContentLoaded', function() {
    poModal = new bootstrap.Modal(document.getElementById('poModal'));
    rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
});

function viewPO(poId) {
    const modalBody = document.getElementById('poModalBody');
    modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border"></div></div>';
    
    poModal.show();
    
    fetch(`${BASE_PATH}/api/purchase-orders.php?id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPODetails(data.data);
            } else {
                modalBody.innerHTML = '<div class="alert alert-danger">Failed to load purchase order</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<div class="alert alert-danger">Error loading purchase order</div>';
        });
}

function displayPODetails(po) {
    const statusMap = {
        'B': 'Pending Received',
        'E': 'Partially Received',
        'F': 'Pending Bill',
        'H': 'Fully Billed'
    };
    
    const canEdit = ['B', 'E'].includes(po.status);
    
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
                ${!canEdit ? '<p class="text-muted"><small>Dates can only be edited when status is Pending/Partially Received</small></p>' : ''}
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label required-field"><strong>Vessel Name</strong></label>
                <input required type="text" class="form-control" id="editVesselName" value="${po.vessel_name || ''}" ${!canEdit ? 'disabled' : ''}>
            </div>
            <div class="col-md-4">
                <label class="form-label required-field"><strong>Vessel Identifier</strong></label>
                <input required type="text" class="form-control" id="editVesselIdentifier" value="${po.vessel_identifier || ''}" ${!canEdit ? 'disabled' : ''}>
            </div>
            <div class="col-md-4">
                <label class="form-label required-field"><strong>Ex-Factory Date</strong> <span class="field-info">(Date items depart factory)</span></label>
                <input required type="date" class="form-control" id="editExpectedFactoryDate" value="${po.expected_factory_date || ''}" ${!canEdit ? 'disabled' : ''}>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label required-field"><strong>Vessel Onboard Date</strong> <span class="field-info">(Date items boards ship)</span></label>
                <input required type="date" class="form-control" id="editPortDate" value="${po.port_date || ''}" ${!canEdit ? 'disabled' : ''}>
            </div>
            <div class="col-md-4">
                <label class="form-label required-field"><strong>Vessel Ship Date</strong> <span class="field-info">(Date ship departs origin)</span></label>
                <input required type="date" class="form-control" id="editShipDate" value="${po.ship_date || ''}" ${!canEdit ? 'disabled' : ''}>
            </div>
            <div class="col-md-4">
                <label class="form-label required-field"><strong>US Delivery Date</strong> <span class="field-info">(Date items arrive at destination)</span></label>
                <input required type="date" class="form-control" id="editEstDelivery" value="${po.estimated_delivery_date || ''}" ${!canEdit ? 'disabled' : ''}>
            </div>
        </div>
        
        <div class="mb-3">
            ${canEdit ? `<button class="btn btn-primary" onclick="savePOChanges(${po.id})">
                <i class="bi bi-save"></i> Submit Changes
            </button>` : ''}
            ${canEdit ? `<button class="btn btn-danger" onclick="showRejectModal(${po.id})">
                <i class="bi bi-x-circle"></i> Reject PO
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
                            ${po.items.map((item, idx) => `
                                <tr>
                                    <td>${escapeHtml(item.item_name)}</td>
                                    <td>${item.quantity || 0}</td>
                                    <td>
                                        ${canEdit ? 
                                            `<input type="number" class="form-control form-control-sm" id="vendorQty_${item.id}" value="${item.vendor_quantity || item.quantity || 0}" min="0" step="0.01">` 
                                            : `${item.vendor_quantity || item.quantity || 0}`
                                        }
                                    </td>
                                    <td>$${parseFloat(item.amount || 0).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
                ${canEdit ? `
                    <div class="mt-3">
                        <button class="btn btn-sm btn-success" onclick="saveVendorQtyChanges(${po.id})">
                            <i class="bi bi-save"></i> Submit Quantity Changes
                        </button>
                    </div>
                ` : ''}
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
                <div class="mb-3">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="po_id" value="${po.id}">
                        <div class="mb-2">
                            <label class="form-label"><strong>Document Type</strong></label>
                            <select name="document_type" class="form-select" id="documentType" required>
                                <option value="">-- Select Document Type --</option>
                                <option value="BOL">BOL (Bill of Lading)</option>
                                <option value="Invoice">Invoice</option>
                                <option value="Receipt">Receipt</option>
                                <option value="Bills">Bills</option>
                                <option value="Other">Other (specify)</option>
                            </select>
                        </div>
                        <div class="mb-2" id="otherSpecifyDiv" style="display:none;">
                            <input type="text" class="form-control" name="other_specify" id="otherSpecify" placeholder="Please specify document type...">
                        </div>
                        <div class="mb-2">
                            <label class="form-label"><strong>File</strong></label>
                            <input type="file" class="form-control" id="fileInput" name="file" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label"><strong>Comments (optional)</strong></label>
                            <textarea class="form-control" name="comment" rows="2" placeholder="Optional comment..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-upload"></i> Upload Document
                        </button>
                    </form>
                </div>
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
    
    // Setup upload form handler
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        uploadDocument(po.id);
    });
    
    // Handle "Other" document type option
    const docTypeSelect = document.getElementById('documentType');
    if (docTypeSelect) {
        docTypeSelect.addEventListener('change', function() {
            const otherDiv = document.getElementById('otherSpecifyDiv');
            if (this.value === 'Other') {
                otherDiv.style.display = 'block';
                document.getElementById('otherSpecify').focus();
            } else {
                otherDiv.style.display = 'none';
            }
        });
    }
}

function savePOChanges(poId) {
    const data = {
        id: poId,
        vessel_name: document.getElementById('editVesselName').value,
        vessel_identifier: document.getElementById('editVesselIdentifier').value,
        expected_factory_date: document.getElementById('editExpectedFactoryDate').value,
        port_date: document.getElementById('editPortDate').value,
        estimated_delivery_date: document.getElementById('editEstDelivery').value,
        ship_date: document.getElementById('editShipDate').value
    };

    if ((data.vessel_name === '') || (data.vessel_identifier === '') || (data.expected_factory_date === '') ||
        (data.port_date === '') || (data.estimated_delivery_date === '') || (data.ship_date === '')) {
        showToast('Please fill in all fields', 'error');
        return;
    }
    
    fetch(`${BASE_PATH}/api/purchase-orders.php`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save changes', 'error');
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
            viewPO(poId);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to add comment', 'error');
    });
}

function saveVendorQtyChanges(poId) {
    // Get all vendor quantity inputs
    const qtyInputs = document.querySelectorAll('[id^="vendorQty_"]');
    const updates = [];
    
    qtyInputs.forEach(input => {
        const itemId = input.id.replace('vendorQty_', '');
        const qty = input.value;
        if (qty) {
            updates.push({ item_id: itemId, vendor_quantity: qty });
        }
    });
    
    if (updates.length === 0) {
        showToast('No quantity changes to save', 'info');
        return;
    }
    
    fetch(`${BASE_PATH}/api/purchase-orders.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_vendor_quantities',
            po_id: poId,
            items: updates
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            viewPO(poId);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save quantity changes', 'error');
    });
}

function uploadDocument(poId) {
    const form = document.getElementById('uploadForm');
    const formData = new FormData(form);
    
    // Handle "Other" document type - combine with specification
    let docType = formData.get('document_type');
    if (docType === 'Other') {
        const otherSpecify = formData.get('other_specify');
        if (!otherSpecify || !otherSpecify.trim()) {
            showToast('Please specify the document type for "Other"', 'error');
            return;
        }
        docType = `Other: ${otherSpecify}`;
        formData.set('document_type', docType);
    }
    
    fetch(`${BASE_PATH}/api/upload.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            viewPO(poId);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to upload document', 'error');
    });
}

function showRejectModal(poId) {
    currentRejectPoId = poId;
    document.getElementById('rejectionReason').value = '';
    poModal.hide();
    rejectModal.show();
}

function submitRejection() {
    const reason = document.getElementById('rejectionReason').value.trim();
    
    if (!reason) {
        showToast('Rejection reason is required', 'error');
        return;
    }
    
    fetch(`${BASE_PATH}/api/purchase-orders.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'reject_po',
            po_id: currentRejectPoId,
            rejection_reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            rejectModal.hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to reject PO', 'error');
    });
}

function uploadDoc(poId) {
    viewPO(poId);
    // Switch to documents tab after modal opens
    setTimeout(() => {
        const documentsTab = document.querySelector('a[href="#documentsTab"]');
        if (documentsTab) {
            const tab = new bootstrap.Tab(documentsTab);
            tab.show();
        }
    }, 500);
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString();
}

function formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
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

// Filter functionality
document.getElementById('searchInput').addEventListener('input', filterTable);
document.getElementById('statusFilter').addEventListener('change', filterTable);
document.getElementById('dateFilter').addEventListener('change', filterTable);

function filterTable() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const dateFilter = document.getElementById('dateFilter').value;
    
    const rows = document.querySelectorAll('#poTable tbody tr[data-po-id]');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const status = row.dataset.status;
        const date = row.dataset.date;
        
        let show = true;
        
        if (searchTerm && !text.includes(searchTerm)) {
            show = false;
        }
        
        if (statusFilter && status !== statusFilter) {
            show = false;
        }
        
        if (dateFilter && date !== dateFilter) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFilter').value = '';
    filterTable();
}
</script>

<?php
function getStatusColor($status) {
    $colors = [
        'B' => 'warning',
        'E' => 'info',
        'F' => 'primary',
        'H' => 'success'
    ];
    return $colors[$status] ?? 'secondary';
}

include __DIR__ . '/../includes/footer.php';
?>