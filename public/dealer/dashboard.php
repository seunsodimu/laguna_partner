<?php
/**
 * Dealer Dashboard
 * View items and manage notifications
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Auth;

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
if ($user['type'] !== 'dealer') {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$pageTitle = 'Dealer Dashboard - Items';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Item Inventory</h2>
            <p class="text-muted">Browse items and set up notifications for stock changes</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" id="viewNotificationsBtn">
                <i class="bi bi-bell"></i> My Notifications
                <span class="badge bg-light text-dark ms-1" id="notificationCount">0</span>
            </button>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Search by item name or SKU...">
            </div>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-outline-secondary" id="clearSearchBtn">Clear Search</button>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="itemsTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>SKU</th>
                            <th>Quantity Available</th>
                            <th>Status</th>
                            <th>Notification</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        <tr>
                            <td colspan="5" class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav aria-label="Items pagination" id="paginationContainer" style="display: none;">
                <ul class="pagination justify-content-center" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Notification Setup Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Set Up Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="notificationItemId">
                <div class="mb-3">
                    <label class="form-label fw-bold">Item:</label>
                    <p id="notificationItemName" class="text-muted"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notification Type</label>
                    <select class="form-select" id="notificationType">
                        <option value="">Select notification type...</option>
                        <option value="in_stock">Notify when item becomes available</option>
                        <option value="out_of_stock">Notify when item goes out of stock</option>
                        <option value="low_stock">Notify when quantity drops below threshold</option>
                    </select>
                </div>
                <div class="mb-3" id="thresholdContainer" style="display: none;">
                    <label class="form-label">Threshold Quantity</label>
                    <input type="number" class="form-control" id="thresholdQuantity" min="1" value="10">
                    <small class="text-muted">You'll be notified when quantity drops below this number</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveNotificationBtn">Save Notification</button>
            </div>
        </div>
    </div>
</div>

<!-- My Notifications Modal -->
<div class="modal fade" id="myNotificationsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">My Notifications</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>SKU</th>
                                <th>Current Qty</th>
                                <th>Notification Type</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="notificationsTableBody">
                            <tr>
                                <td colspan="5" class="text-center">
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
</div>

<script>
const BASE_PATH = '<?= BASE_PATH ?>';
let currentPage = 1;
let searchTerm = '';
let notificationModal, myNotificationsModal;

document.addEventListener('DOMContentLoaded', function() {
    notificationModal = new bootstrap.Modal(document.getElementById('notificationModal'));
    myNotificationsModal = new bootstrap.Modal(document.getElementById('myNotificationsModal'));
    
    loadItems();
    loadNotificationCount();
    
    // Search functionality
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchTerm = this.value;
            currentPage = 1;
            loadItems();
        }, 500);
    });
    
    document.getElementById('clearSearchBtn').addEventListener('click', function() {
        document.getElementById('searchInput').value = '';
        searchTerm = '';
        currentPage = 1;
        loadItems();
    });
    
    // Notification type change
    document.getElementById('notificationType').addEventListener('change', function() {
        const thresholdContainer = document.getElementById('thresholdContainer');
        if (this.value === 'low_stock') {
            thresholdContainer.style.display = 'block';
        } else {
            thresholdContainer.style.display = 'none';
        }
    });
    
    // Save notification
    document.getElementById('saveNotificationBtn').addEventListener('click', saveNotification);
    
    // View notifications
    document.getElementById('viewNotificationsBtn').addEventListener('click', function() {
        loadMyNotifications();
        myNotificationsModal.show();
    });
});

function loadItems() {
    const tbody = document.getElementById('itemsTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';
    
    const params = new URLSearchParams({
        page: currentPage,
        search: searchTerm
    });
    
    fetch(`${BASE_PATH}/api/items.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayItems(data.data);
                displayPagination(data.pagination);
            } else {
                showToast('Error loading items: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load items', 'error');
        });
}

function displayItems(items) {
    const tbody = document.getElementById('itemsTableBody');
    
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No items found</td></tr>';
        return;
    }
    
    tbody.innerHTML = items.map(item => {
        const qty = parseInt(item.quantity_available) || 0;
        const hasNotification = parseInt(item.has_notification) > 0;
        
        let statusBadge = '';
        if (qty > 0) {
            statusBadge = '<span class="badge bg-success">In Stock</span>';
        } else {
            statusBadge = '<span class="badge bg-danger">Out of Stock</span>';
        }
        
        let notificationBtn = '';
        if (hasNotification) {
            const notifType = item.notification_type;
            let notifText = '';
            if (notifType === 'in_stock') notifText = 'Notify: In Stock';
            else if (notifType === 'out_of_stock') notifText = 'Notify: Out of Stock';
            else if (notifType === 'low_stock') notifText = `Notify: Below ${item.threshold_quantity}`;
            
            notificationBtn = `<button class="btn btn-sm btn-success" onclick="setupNotification(${item.id}, '${escapeHtml(item.item_name)}', ${qty})">
                <i class="bi bi-bell-fill"></i> ${notifText}
            </button>`;
        } else {
            if (qty > 0) {
                notificationBtn = `<button class="btn btn-sm btn-outline-primary" onclick="setupNotification(${item.id}, '${escapeHtml(item.item_name)}', ${qty})">
                    <i class="bi bi-bell"></i> Notify: Out of Stock / Low
                </button>`;
            } else {
                notificationBtn = `<button class="btn btn-sm btn-outline-primary" onclick="setupNotification(${item.id}, '${escapeHtml(item.item_name)}', ${qty})">
                    <i class="bi bi-bell"></i> Notify: In Stock
                </button>`;
            }
        }
        
        return `
            <tr>
                <td>${escapeHtml(item.item_name)}</td>
                <td><code>${escapeHtml(item.sku)}</code></td>
                <td><strong>${qty}</strong></td>
                <td>${statusBadge}</td>
                <td>${notificationBtn}</td>
            </tr>
        `;
    }).join('');
}

function displayPagination(pagination) {
    const container = document.getElementById('paginationContainer');
    const paginationEl = document.getElementById('pagination');
    
    if (pagination.total_pages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    
    let html = '';
    
    // Previous button
    html += `<li class="page-item ${pagination.page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="changePage(${pagination.page - 1}); return false;">Previous</a>
    </li>`;
    
    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === 1 || i === pagination.total_pages || (i >= pagination.page - 2 && i <= pagination.page + 2)) {
            html += `<li class="page-item ${i === pagination.page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.page - 3 || i === pagination.page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Next button
    html += `<li class="page-item ${pagination.page === pagination.total_pages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="changePage(${pagination.page + 1}); return false;">Next</a>
    </li>`;
    
    paginationEl.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    loadItems();
    window.scrollTo(0, 0);
}

function setupNotification(itemId, itemName, currentQty) {
    document.getElementById('notificationItemId').value = itemId;
    document.getElementById('notificationItemName').textContent = itemName + ' (Current Qty: ' + currentQty + ')';
    
    // Pre-select notification type based on current quantity
    const notificationType = document.getElementById('notificationType');
    if (currentQty > 0) {
        notificationType.value = 'out_of_stock';
    } else {
        notificationType.value = 'in_stock';
    }
    
    document.getElementById('thresholdContainer').style.display = 'none';
    
    notificationModal.show();
}

function saveNotification() {
    const itemId = document.getElementById('notificationItemId').value;
    const notificationType = document.getElementById('notificationType').value;
    const thresholdQuantity = document.getElementById('thresholdQuantity').value;
    
    if (!notificationType) {
        showToast('Please select a notification type', 'error');
        return;
    }
    
    if (notificationType === 'low_stock' && (!thresholdQuantity || thresholdQuantity < 1)) {
        showToast('Please enter a valid threshold quantity', 'error');
        return;
    }
    
    const btn = document.getElementById('saveNotificationBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    
    fetch(`${BASE_PATH}/api/items.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'subscribe',
            item_id: itemId,
            notification_type: notificationType,
            threshold_quantity: thresholdQuantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            notificationModal.hide();
            loadItems();
            loadNotificationCount();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save notification', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Save Notification';
    });
}

function loadMyNotifications() {
    const tbody = document.getElementById('notificationsTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary"></div></td></tr>';
    
    fetch(`${BASE_PATH}/api/items.php?action=notifications`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayNotifications(data.data);
            } else {
                showToast('Error loading notifications: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load notifications', 'error');
        });
}

function displayNotifications(notifications) {
    const tbody = document.getElementById('notificationsTableBody');
    
    if (notifications.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No active notifications</td></tr>';
        return;
    }
    
    tbody.innerHTML = notifications.map(notif => {
        let notifTypeText = '';
        if (notif.notification_type === 'in_stock') notifTypeText = 'In Stock';
        else if (notif.notification_type === 'out_of_stock') notifTypeText = 'Out of Stock';
        else if (notif.notification_type === 'low_stock') notifTypeText = `Low Stock (Below ${notif.threshold_quantity})`;
        
        return `
            <tr>
                <td>${escapeHtml(notif.item_name)}</td>
                <td><code>${escapeHtml(notif.sku)}</code></td>
                <td>${notif.quantity_available || 0}</td>
                <td><span class="badge bg-info">${notifTypeText}</span></td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="deleteNotification(${notif.id})">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function deleteNotification(notificationId) {
    if (!confirm('Are you sure you want to delete this notification?')) {
        return;
    }
    
    fetch(`${BASE_PATH}/api/items.php`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: notificationId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            loadMyNotifications();
            loadItems();
            loadNotificationCount();
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to delete notification', 'error');
    });
}

function loadNotificationCount() {
    fetch(`${BASE_PATH}/api/items.php?action=notifications`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('notificationCount').textContent = data.data.length;
            }
        })
        .catch(error => console.error('Error loading notification count:', error));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    // Simple toast implementation
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '9999';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>