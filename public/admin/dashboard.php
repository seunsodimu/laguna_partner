<?php
/**
 * Admin Dashboard
 * Manage syncs, users, and system settings
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
define('BASE_PATH', getenv('APP_BASE_PATH') ?: '/laguna_partner');

session_start();

// Check authentication and user type
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

// Get statistics
$stats = [
    'accounts' => $db->fetchOne("SELECT COUNT(*) as count FROM accounts")['count'],
    'users' => $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
    'pos' => $db->fetchOne("SELECT COUNT(*) as count FROM purchase_orders")['count'],
    'items' => $db->fetchOne("SELECT COUNT(*) as count FROM items")['count'],
    'pending_updates' => $db->fetchOne("SELECT COUNT(*) as count FROM purchase_orders WHERE has_vendor_updates = 1")['count']
];

// Get recent sync logs
$recentSyncs = $db->fetchAll(
    "SELECT * FROM sync_logs ORDER BY started_at DESC LIMIT 10"
);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <h2 class="mb-4">Admin Dashboard</h2>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Accounts</h6>
                    <h3><?php echo $stats['accounts']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Users</h6>
                    <h3><?php echo $stats['users']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Purchase Orders</h6>
                    <h3><?php echo $stats['pos']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Items</h6>
                    <h3><?php echo $stats['items']; ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($stats['pending_updates'] > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong><?php echo $stats['pending_updates']; ?></strong> purchase order(s) have pending vendor updates
    </div>
    <?php endif; ?>
    
    <!-- Manual Sync Controls -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Manual Synchronization</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>Accounts & Users</h6>
                            <p class="text-muted small">Sync vendors, dealers, and buyers from NetSuite</p>
                            <button class="btn btn-primary w-100" onclick="runSync('accounts')">
                                <i class="bi bi-arrow-repeat"></i> Sync Accounts
                            </button>
                            <div id="accountsSyncStatus" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>Purchase Orders</h6>
                            <p class="text-muted small">Sync all open purchase orders from NetSuite</p>
                            <button class="btn btn-primary w-100" onclick="runSync('purchase-orders')">
                                <i class="bi bi-arrow-repeat"></i> Sync Purchase Orders
                            </button>
                            <div id="purchase-ordersSyncStatus" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6>Items</h6>
                            <p class="text-muted small">Sync item inventory from NetSuite</p>
                            <button class="btn btn-primary w-100" onclick="runSync('items')">
                                <i class="bi bi-arrow-repeat"></i> Sync Items
                            </button>
                            <div id="itemsSyncStatus" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Sync Logs -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Recent Sync Logs</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Duration</th>
                            <th>Records</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSyncs)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No sync logs yet</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recentSyncs as $sync): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($sync['sync_type']); ?></span></td>
                            <td>
                                <?php if ($sync['status'] === 'success'): ?>
                                <span class="badge bg-success">Success</span>
                                <?php elseif ($sync['status'] === 'failed'): ?>
                                <span class="badge bg-danger">Failed</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Running</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($sync['started_at'])); ?></td>
                            <td><?php echo $sync['completed_at'] ? date('Y-m-d H:i:s', strtotime($sync['completed_at'])) : 'N/A'; ?></td>
                            <td>
                                <?php 
                                if ($sync['completed_at']) {
                                    $start = strtotime($sync['started_at']);
                                    $end = strtotime($sync['completed_at']);
                                    echo round($end - $start, 2) . 's';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><?php echo $sync['records_processed'] ?? 0; ?></td>
                            <td><?php echo htmlspecialchars($sync['error_message'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">User Management</h5>
                </div>
                <div class="card-body">
                    <a href="<?= BASE_PATH ?>/admin/users.php" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-people"></i> Manage Users
                    </a>
                    <a href="<?= BASE_PATH ?>/admin/user-logs.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-clock-history"></i> View User Logs
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">System Settings</h5>
                </div>
                <div class="card-body">
                    <a href="<?= BASE_PATH ?>/admin/settings.php" class="btn btn-outline-warning w-100 mb-2">
                        <i class="bi bi-gear"></i> NetSuite Environment
                    </a>
                    <a href="<?= BASE_PATH ?>/admin/email-templates.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-envelope"></i> Email Templates
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Current Environment</h5>
                </div>
                <div class="card-body text-center">
                    <?php
                    $envFile = __DIR__ . '/../../.env';
                    $currentEnv = 'sandbox';
                    if (file_exists($envFile)) {
                        $envContent = file_get_contents($envFile);
                        if (preg_match('/NETSUITE_ENVIRONMENT=(\w+)/', $envContent, $matches)) {
                            $currentEnv = $matches[1];
                        }
                    }
                    ?>
                    <h3 class="mb-2">
                        <span class="badge bg-<?php echo $currentEnv === 'production' ? 'danger' : 'info'; ?> fs-5">
                            <?php echo strtoupper($currentEnv); ?>
                        </span>
                    </h3>
                    <p class="text-muted small mb-0">NetSuite Environment</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Management Section -->
    <h3 class="mt-5 mb-3">Data Management</h3>
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Purchase Orders</h5>
                </div>
                <div class="card-body">
                    <a href="<?= BASE_PATH ?>/admin/purchase-orders.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-file-earmark-check"></i> View & Filter
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Invoices</h5>
                </div>
                <div class="card-body">
                    <a href="<?= BASE_PATH ?>/admin/invoices.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-receipt"></i> View & Filter
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">System Logs</h5>
                </div>
                <div class="card-body">
                    <a href="<?= BASE_PATH ?>/admin/logs.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-file-text"></i> View Log Files
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Production Reset Section -->
    <h3 class="mt-5 mb-3">Production Reset</h3>
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Reset Portal Data</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Clear all user accounts (except admin), purchase orders, invoices, and related data to make the portal production-ready. 
                        <strong class="text-danger">This action cannot be undone!</strong>
                    </p>
                    <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#resetConfirmModal">
                        <i class="bi bi-trash"></i> Reset Portal
                    </button>
                    <div id="resetStatus" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Confirmation Modal -->
<div class="modal fade" id="resetConfirmModal" tabindex="-1" aria-labelledby="resetConfirmLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="resetConfirmLabel"><i class="bi bi-exclamation-circle"></i> Confirm Portal Reset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" role="alert">
                    <strong>WARNING:</strong> This action will permanently delete:
                </div>
                <ul class="mb-3">
                    <li>All user accounts (except admin)</li>
                    <li>All purchase orders and documents</li>
                    <li>All invoices and attachments</li>
                    <li>All messages and conversations</li>
                    <li>All payment records</li>
                    <li>All sync logs and user logs</li>
                    <li>All vendor profiles and documents</li>
                </ul>
                <p class="text-muted">
                    <strong>This action CANNOT be undone.</strong> Please ensure you have a database backup before proceeding.
                </p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmReset" required>
                    <label class="form-check-label" for="confirmReset">
                        I understand this will permanently delete all data and I have backed up the database
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmResetBtn" onclick="executeReset()" disabled>
                    <i class="bi bi-trash"></i> Proceed with Reset
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function runSync(type) {
    const statusDiv = document.getElementById(type + 'SyncStatus');
    const btn = event.target;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';
    statusDiv.innerHTML = '<div class="alert alert-info mb-0 mt-2">Sync in progress...</div>';
    
    fetch('<?= BASE_PATH ?>/api/sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: type })
    })
    .then(response => {
        // Check if response is ok (status 200-299)
        if (!response.ok) {
            // Try to parse JSON error response
            return response.json().then(data => {
                throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
            }).catch(jsonError => {
                // If JSON parsing fails, throw HTTP error
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = `<div class="alert alert-success mb-0 mt-2">
                <i class="bi bi-check-circle"></i> ${data.message}
                ${data.records ? `<br><small>${data.records} records processed</small>` : ''}
            </div>`;
            
            // Reload page after 2 seconds to update stats
            setTimeout(() => location.reload(), 2000);
        } else {
            // Show detailed error information
            let errorHtml = `<div class="alert alert-danger mb-0 mt-2">
                <i class="bi bi-x-circle"></i> <strong>Error:</strong> ${escapeHtml(data.message)}`;
            
            // Add file and line info if available
            if (data.file) {
                errorHtml += `<br><small class="text-muted">File: ${escapeHtml(data.file)}</small>`;
            }
            if (data.line) {
                errorHtml += `<br><small class="text-muted">Line: ${data.line}</small>`;
            }
            
            // Add trace in a collapsible section if available
            if (data.trace) {
                errorHtml += `<br><br>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#trace-${type}">
                        Show Stack Trace
                    </button>
                    <div class="collapse mt-2" id="trace-${type}">
                        <pre class="bg-light p-2 small" style="max-height: 200px; overflow-y: auto;">${escapeHtml(data.trace)}</pre>
                    </div>`;
            }
            
            errorHtml += '</div>';
            statusDiv.innerHTML = errorHtml;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = `<div class="alert alert-danger mb-0 mt-2">
            <i class="bi bi-x-circle"></i> <strong>Failed to run sync:</strong><br>
            ${escapeHtml(error.message || error.toString())}
        </div>`;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sync ' + type.charAt(0).toUpperCase() + type.slice(1);
    });
}

// Helper function to escape HTML and prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('confirmReset').addEventListener('change', function() {
    document.getElementById('confirmResetBtn').disabled = !this.checked;
});

function executeReset() {
    const statusDiv = document.getElementById('resetStatus');
    const modal = bootstrap.Modal.getInstance(document.getElementById('resetConfirmModal'));
    const btn = document.getElementById('confirmResetBtn');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Resetting...';
    
    fetch('<?= BASE_PATH ?>/api/reset.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ confirm: true })
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
            }).catch(jsonError => {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            modal.hide();
            statusDiv.innerHTML = `<div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <strong>Reset Complete!</strong>
                <br>Portal has been reset successfully.
                <br><small>${data.deleted_records} records deleted in ${data.duration_ms}ms</small>
                <br><button class="btn btn-sm btn-primary mt-2" onclick="location.reload()">Reload Page</button>
            </div>`;
        } else {
            statusDiv.innerHTML = `<div class="alert alert-danger">
                <i class="bi bi-x-circle"></i> <strong>Error:</strong> ${escapeHtml(data.message)}
            </div>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusDiv.innerHTML = `<div class="alert alert-danger">
            <i class="bi bi-x-circle"></i> <strong>Failed to reset:</strong><br>
            ${escapeHtml(error.message || error.toString())}
        </div>`;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-trash"></i> Proceed with Reset';
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>