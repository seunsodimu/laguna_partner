<?php
/**
 * Admin - User Management
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Define base path for redirects
define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

session_start();

// Check if user is logged in and is admin
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

// Get filter parameters
$userType = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($userType) {
    $where[] = "u.type = ?";
    $params[] = $userType;
}

if ($search) {
    $where[] = "(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get users with account count
$users = $db->fetchAll(
    "SELECT u.*, 
            COUNT(DISTINCT ua.account_id) as account_count,
            MAX(ul.created_at) as last_activity
     FROM users u
     LEFT JOIN user_accounts ua ON u.id = ua.user_id
     LEFT JOIN user_logs ul ON u.id = ul.user_id
     $whereClause
     GROUP BY u.id
     ORDER BY u.created_at DESC",
    $params
);

$pageTitle = 'User Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people"></i> User Management</h2>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus"></i> Add User
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">User Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="admin" <?= $userType === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="buyer" <?= $userType === 'buyer' ? 'selected' : '' ?>>Buyer</option>
                        <option value="vendor" <?= $userType === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                        <option value="dealer" <?= $userType === 'dealer' ? 'selected' : '' ?>>Dealer</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Email, name..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="/admin/users.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Accounts</th>
                            <th>Last Activity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'])) ?: '-' ?></td>
                            <td>
                                <span class="badge bg-<?= 
                                    ($u['type'] === 'user' && $u['role'] === 'admin') ? 'danger' : 
                                    ($u['type'] === 'user' ? 'primary' : 
                                    ($u['type'] === 'vendor' ? 'success' : 'info')) 
                                ?>">
                                    <?= $u['type'] === 'user' ? ucfirst($u['role']) : ucfirst($u['type']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="#" onclick="viewUserAccounts(<?= $u['id'] ?>); return false;">
                                    <?= $u['account_count'] ?> account(s)
                                </a>
                            </td>
                            <td>
                                <?php if ($u['last_activity']): ?>
                                    <small><?= date('M j, Y g:i A', strtotime($u['last_activity'])) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Never</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $u['id'] ?>)" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info" onclick="viewUserLogs(<?= $u['id'] ?>)" title="View Logs">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                                <?php if ($u['id'] !== $user['id']): ?>
                                <button class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>" 
                                        onclick="toggleUserStatus(<?= $u['id'] ?>, <?= $u['is_active'] ? 0 : 1 ?>)" 
                                        title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <i class="bi bi-<?= $u['is_active'] ? 'pause' : 'play' ?>-circle"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                No users found
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">User Type *</label>
                        <select class="form-select" name="type" required>
                            <option value="">Select Type</option>
                            <option value="admin">Admin</option>
                            <option value="buyer">Buyer</option>
                            <option value="vendor">Vendor</option>
                            <option value="dealer">Dealer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NetSuite ID</label>
                        <input type="number" class="form-control" name="netsuite_id" placeholder="NetSuite ID (optional)">
                    </div>
                    <div class="alert alert-info">
                        <small><i class="bi bi-info-circle"></i> User will receive an OTP to their email for login.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveNewUser()">Add User</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="editUserBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- User Accounts Modal -->
<div class="modal fade" id="userAccountsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Accounts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userAccountsBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- User Logs Modal -->
<div class="modal fade" id="userLogsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Activity Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userLogsBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
function saveNewUser() {
    const form = document.getElementById('addUserForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    fetch('/api/users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('User added successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message || 'Error adding user', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error adding user', 'error');
    });
}

function editUser(userId) {
    fetch(`/api/users.php?id=${userId}`)
        .then(response => response.json())
        .then(user => {
            const html = `
                <form id="editUserForm">
                    <input type="hidden" name="id" value="${user.id}">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="${escapeHtml(user.email)}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control" name="first_name" value="${escapeHtml(user.first_name || '')}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control" name="last_name" value="${escapeHtml(user.last_name || '')}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">User Type</label>
                        <select class="form-select" name="type" required onchange="updateRoleOptions(this)">
                            <option value="user" ${user.type === 'user' ? 'selected' : ''}>Internal User</option>
                            <option value="vendor" ${user.type === 'vendor' ? 'selected' : ''}>Vendor</option>
                            <option value="dealer" ${user.type === 'dealer' ? 'selected' : ''}>Dealer</option>
                        </select>
                    </div>
                    <div class="mb-3" id="roleDiv" style="${user.type === 'user' ? '' : 'display:none;'}">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="">-- Select Role --</option>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                            <option value="buyer" ${user.role === 'buyer' ? 'selected' : ''}>Buyer</option>
                            <option value="accounting" ${user.role === 'accounting' ? 'selected' : ''}>Accounting</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NetSuite ID</label>
                        <input type="number" class="form-control" name="netsuite_id" value="${escapeHtml(user.netsuite_id || '')}">
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveUserChanges()">Save Changes</button>
                </div>
            `;
            document.getElementById('editUserBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        });
}

function saveUserChanges() {
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    fetch('/api/users.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('User updated successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message || 'Error updating user', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error updating user', 'error');
    });
}

function updateRoleOptions(typeSelect) {
    const roleDiv = document.getElementById('roleDiv');
    if (typeSelect.value === 'user') {
        roleDiv.style.display = '';
    } else {
        roleDiv.style.display = 'none';
    }
}

function toggleUserStatus(userId, newStatus) {
    if (!confirm(`Are you sure you want to ${newStatus ? 'activate' : 'deactivate'} this user?`)) {
        return;
    }
    
    fetch('/api/users.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: userId, is_active: newStatus })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast('User status updated', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message || 'Error updating status', 'error');
        }
    });
}

function viewUserAccounts(userId) {
    fetch(`/api/users.php?id=${userId}&include=accounts`)
        .then(response => response.json())
        .then(data => {
            const html = `
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Account ID</th>
                                <th>Company Name</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.accounts.map(acc => `
                                <tr>
                                    <td>${acc.id}</td>
                                    <td>${escapeHtml(acc.company_name)}</td>
                                    <td><span class="badge bg-secondary">${acc.type}</span></td>
                                    <td><span class="badge bg-${acc.is_active ? 'success' : 'secondary'}">${acc.is_active ? 'Active' : 'Inactive'}</span></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            document.getElementById('userAccountsBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('userAccountsModal')).show();
        });
}

function viewUserLogs(userId) {
    fetch(`/api/users.php?id=${userId}&include=logs`)
        .then(response => response.json())
        .then(data => {
            const html = `
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.logs.map(log => `
                                <tr>
                                    <td><small>${formatDateTime(log.created_at)}</small></td>
                                    <td><span class="badge bg-info">${log.action}</span></td>
                                    <td><small>${escapeHtml(log.details || '-')}</small></td>
                                    <td><small>${log.ip_address}</small></td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            document.getElementById('userLogsBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('userLogsModal')).show();
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateStr) {
    if (!dateStr || dateStr === '0000-00-00' || dateStr === '1970-01-01') return '-';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleString();
}

function showToast(message, type = 'info') {
    // Simple toast implementation
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type} position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '9999';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>