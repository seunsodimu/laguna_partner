<?php
/**
 * Purchase Order Details Page
 * Dedicated page for reviewing individual purchase orders
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
$po_id = intval($_GET['id'] ?? 0);

if (!$po_id) {
    header('Location: ' . BASE_PATH . '/buyer/dashboard.php');
    exit;
}

$po = $db->fetchOne(
    "SELECT po.*, 
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as buyer_name,
            u.email as buyer_email
     FROM purchase_orders po
     LEFT JOIN users u ON po.buyer_id = u.id
     WHERE po.id = ?",
    [$po_id]
);
if (!$po) {
    header('Location: ' . BASE_PATH . '/buyer/dashboard.php');
    exit;
}

$po['items'] = $db->fetchAll("SELECT * FROM po_items WHERE po_id = ? ORDER BY item_id", [$po_id]);
$po['comments'] = $db->fetchAll("SELECT * FROM po_comments WHERE po_id = ? ORDER BY created_at DESC", [$po_id]);
$po['documents'] = $db->fetchAll("SELECT * FROM po_documents WHERE po_id = ? ORDER BY created_at DESC", [$po_id]);

$pageTitle = 'Purchase Order #' . htmlspecialchars($po['po_number'] ?? $po['tran_id']);
include __DIR__ . '/../includes/header.php';
?>

<style>
    .po-container {
        max-width: 1100px;
        margin: 30px auto;
    }
    
    .po-header {
        background: #1e8449;
        color: white;
        padding: 30px;
        border-radius: 8px 8px 0 0;
        margin-bottom: 0;
    }
    
    .po-header h1 {
        margin: 0;
        font-size: 2rem;
        font-weight: 700;
    }
    
    .po-header p {
        margin: 5px 0 0 0;
        opacity: 0.9;
    }
    
    .po-details {
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
        border-bottom: 2px solid #27ae60;
        margin-top: 20px;
        margin-bottom: 0;
    }
    
    .editable-fields {
        padding: 20px;
        background: #fafbfc;
    }
    
    .editable-fields .form-group {
        margin-bottom: 15px;
    }
    
    .items-table,
    .comments-list,
    .documents-list {
        padding: 20px;
    }
    
    .comment-item {
        padding: 15px;
        background: #f8f9fa;
        border-left: 4px solid #27ae60;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    
    .comment-meta {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 8px;
    }
    
    .document-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 6px;
        margin-bottom: 10px;
    }
    
    .action-buttons {
        padding: 20px;
        display: flex;
        gap: 10px;
        justify-content: center;
        background: #f8f9fa;
    }
    
    .tab-content {
        padding: 20px 0;
    }
    
    .badge-large {
        padding: 0.5rem 1rem;
        font-size: 0.95rem;
    }
    
    .alert-has-updates {
        background: #fff3cd;
        border: 1px solid #ffc107;
        color: #856404;
        padding: 12px 15px;
        border-radius: 6px;
        margin: 10px 0 0 0;
        width: 30%;
    }
</style>

<div class="po-container">
    <div class="po-header">
        <h1>Purchase Order #<?php echo htmlspecialchars($po['po_number'] ?? $po['tran_id']); ?></h1>
        <p><?php echo htmlspecialchars($po['company_name'] ?? 'N/A'); ?></p>
        <?php if (intval($po['has_vendor_updates'] ?? 0) === 1): ?>
        <div class="alert-has-updates">
            <i class="bi bi-exclamation-triangle me-2"></i> Vendor has made updates
        </div>
        <?php endif; ?>
    </div>
    
    <div class="po-details">
        <div class="detail-row">
            <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <?php
                    $statusMap = [
                        'A' => [ 'text'=>'Pending Approval', 'class' => 'warning' ],
                        'B' => ['text' => 'Pending Received', 'class' => 'warning'],
                        'E' => ['text' => 'Partially Received', 'class' => 'info'],
                        'F' => ['text' => 'Pending Bill', 'class' => 'primary'],
                        'H' => ['text' => 'Fully Billed', 'class' => 'success']
                    ];
                    $status = $statusMap[$po['status']] ?? ['text' => $po['status'], 'class' => 'secondary'];
                    ?>
                    <span class="badge bg-<?php echo $status['class']; ?> badge-large">
                        <?php echo $status['text']; ?>
                    </span>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Total Amount</span>
                <span class="detail-value" style="font-size: 1.3rem; font-weight: 700; color: #27ae60;">
                    $<?php echo number_format($po['total_amount'] ?? 0, 2); ?>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">PO Date</span>
                <span class="detail-value"><?php echo $po['created_date'] ? date('M d, Y', strtotime($po['created_date'])) : 'N/A'; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Assigned To</span>
                <span class="detail-value"><?php echo htmlspecialchars($po['buyer_name'] ?? 'Unassigned'); ?></span>
            </div>
        </div>
        
        <!-- Editable Fields Section -->
        <div class="section-title">
            <i class="bi bi-pencil-square me-2"></i> Details
        </div>
        <div class="editable-fields">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><strong>Vessel Name</strong></label>
                    <input type="text" class="form-control" id="editVesselName" value="<?php echo htmlspecialchars($po['vessel_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><strong>Vessel Identifier</strong></label>
                    <input type="text" class="form-control" id="editVesselIdentifier" value="<?php echo htmlspecialchars($po['vessel_identifier'] ?? ''); ?>">
                    <?php if ($po['vessel_identifier']): ?>
                    <small><a href="https://www.myshiptracking.com/?mmsi=<?php echo urlencode($po['vessel_identifier']); ?>" target="_blank" rel="noopener noreferrer">Track Vessel</a></small>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><strong>Expected Factory Date</strong></label>
                    <input type="date" class="form-control" id="editExpectedFactoryDate" value="<?php echo htmlspecialchars($po['expected_factory_date'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row g-3 mt-3">
                <div class="col-md-4">
                    <label class="form-label"><strong>Port Date</strong></label>
                    <input type="date" class="form-control" id="editPortDate" value="<?php echo htmlspecialchars($po['port_date'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><strong>Est. Delivery Date</strong></label>
                    <input type="date" class="form-control" id="editEstDelivery" value="<?php echo htmlspecialchars($po['estimated_delivery_date'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><strong>Ship Date</strong></label>
                    <input type="date" class="form-control" id="editShipDate" value="<?php echo htmlspecialchars($po['ship_date'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="mt-3">
                <label class="form-label"><strong>Memo</strong></label>
                <textarea class="form-control" id="editMemo" rows="3"><?php echo htmlspecialchars($po['memo'] ?? ''); ?></textarea>
            </div>
            
            <div class="mt-3">
                <button class="btn btn-primary" onclick="savePOChanges(<?php echo $po['id']; ?>)">
                    <i class="bi bi-save me-2"></i> Save Changes
                </button>
                <?php if (intval($po['has_vendor_updates'] ?? 0) === 1): ?>
                <button class="btn btn-success" onclick="approveVendorChanges(<?php echo $po['id']; ?>)">
                    <i class="bi bi-check-circle me-2"></i> Approve & Sync to NetSuite
                </button>
                <button class="btn btn-warning" onclick="rejectVendorChanges(<?php echo $po['id']; ?>)">
                    <i class="bi bi-check-circle me-2"></i> Reject PO
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs" role="tablist" style="padding: 20px 20px 0 20px; background: white; margin-top: 20px;">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#itemsTab">
                    <i class="bi bi-box me-2"></i> Items (<?php echo count($po['items']); ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#commentsTab">
                    <i class="bi bi-chat-left me-2"></i> Comments (<?php echo count($po['comments']); ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#documentsTab">
                    <i class="bi bi-file-earmark me-2"></i> Documents (<?php echo count($po['documents']); ?>)
                </a>
            </li>
        </ul>
        
        <div class="tab-content" style="background: white; border: 1px solid #dee2e6; border-top: none; padding: 20px;">
            <!-- Items Tab -->
            <div class="tab-pane fade show active" id="itemsTab">
                <?php if (!empty($po['items'])): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th class="text-end">Original Qty</th>
                                <th class="text-end">Vendor/Shipped Qty</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($po['items'] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></td>
                                <td class="text-end"><?php echo number_format($item['quantity'] ?? 0, 2); ?></td>
                                <td class="text-end"><?php echo number_format($item['vendor_quantity'] ?? $item['quantity'] ?? 0, 2); ?></td>
                                <td class="text-end">$<?php echo number_format($item['line_total'] ?? 0, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">No items found</p>
                <?php endif; ?>
            </div>
            
            <!-- Comments Tab -->
            <div class="tab-pane fade" id="commentsTab">
                <div class="mb-3">
                    <textarea class="form-control" id="newComment" rows="3" placeholder="Add a comment..."></textarea>
                    <button class="btn btn-sm btn-primary mt-2" onclick="addComment(<?php echo $po['id']; ?>)">
                        <i class="bi bi-send me-2"></i> Add Comment
                    </button>
                </div>
                
                <?php if (!empty($po['comments'])): ?>
                <div id="commentsList">
                    <?php foreach ($po['comments'] as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-meta">
                            <strong><?php echo htmlspecialchars($comment['user_name'] ?? $comment['user_email'] ?? 'Unknown'); ?></strong>
                            <span class="text-muted ms-2">
                                <?php echo date('M d, Y g:i A', strtotime($comment['created_at'])); ?>
                            </span>
                        </div>
                        <p class="mb-0"><?php echo htmlspecialchars($comment['comment']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">No comments yet</p>
                <?php endif; ?>
            </div>
            
            <!-- Documents Tab -->
            <div class="tab-pane fade" id="documentsTab">
                <?php if (!empty($po['documents'])): ?>
                <div class="documents-list">
                    <?php foreach ($po['documents'] as $doc): ?>
                    <div class="document-item">
                        <div>
                            <i class="bi bi-file-earmark"></i>
                            <strong><?php echo htmlspecialchars($doc['original_filename'] ?? $doc['file_name']); ?></strong>
                            <?php if ($doc['document_type']): ?>
                            <span class="badge bg-info ms-2"><?php echo htmlspecialchars($doc['document_type']); ?></span>
                            <?php endif; ?>
                            <small class="text-muted ms-2">
                                (<?php 
                                $size = $doc['file_size'] ?? 0;
                                if ($size === 0) echo '0 Bytes';
                                else {
                                    $k = 1024;
                                    $sizes = ['Bytes', 'KB', 'MB'];
                                    $i = floor(log($size) / log($k));
                                    echo round($size / pow($k, $i) * 100) / 100 . ' ' . $sizes[$i];
                                }
                                ?>)
                            </small>
                            <?php if ($doc['comment']): ?>
                            <p class="mb-0 mt-1 text-muted small"><?php echo htmlspecialchars($doc['comment']); ?></p>
                            <?php endif; ?>
                            <small class="text-muted d-block"><?php echo date('M d, Y g:i A', strtotime($doc['created_at'])); ?></small>
                        </div>
                        <a href="<?php echo BASE_PATH; ?>/api/download.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-outline-primary" title="Download">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted">No documents uploaded</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="<?php echo BASE_PATH; ?>/buyer/dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back to Purchase Orders
            </a>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 11"></div>

<script>
const BASE_PATH = '<?php echo BASE_PATH; ?>';
const PO_ID = <?php echo $po['id']; ?>;

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
            showToast(data.message || 'Changes saved successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('Error: ' + (data.message || 'Failed to save changes'), 'error');
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
            showToast(data.message || 'Changes approved successfully', 'success');
            setTimeout(() => {
                window.location.href = `${BASE_PATH}/buyer/dashboard.php`;
            }, 1500);
        } else {
            showToast('Error: ' + (data.message || 'Failed to approve changes'), 'error');
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
            showToast(data.message || 'Comment added successfully', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('Error: ' + (data.message || 'Failed to add comment'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to add comment', 'error');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
