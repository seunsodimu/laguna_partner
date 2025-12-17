<?php
/**
 * User Logs Viewer
 * View and filter user activity logs
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
if ($user['type'] !== 'user' || $user['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$db = Database::getInstance();

$users = $db->fetchAll("SELECT id, email, first_name, last_name FROM users ORDER BY email");
$entity_types = $db->fetchAll("SELECT DISTINCT entity_type FROM user_logs WHERE entity_type IS NOT NULL ORDER BY entity_type");
$actions = $db->fetchAll("SELECT DISTINCT action FROM user_logs ORDER BY action");

$pageTitle = 'User Logs';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>User Activity Logs</h2>
            <p class="text-muted">View and filter user activity across the system</p>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select class="form-select" id="filterUser">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <select class="form-select" id="filterAction">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $act): ?>
                        <option value="<?php echo htmlspecialchars($act['action']); ?>"><?php echo htmlspecialchars($act['action']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Entity Type</label>
                    <select class="form-select" id="filterEntityType">
                        <option value="">All Entities</option>
                        <?php foreach ($entity_types as $et): ?>
                        <option value="<?php echo htmlspecialchars($et['entity_type']); ?>"><?php echo htmlspecialchars($et['entity_type']); ?></option>
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
                Activity Logs
                <span class="badge bg-secondary ms-2" id="logCount">0 records</span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity Type</th>
                        <th>Entity ID</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody id="logsTable">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Loading...</td>
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
const logsPerPage = 50;

document.addEventListener('DOMContentLoaded', function() {
    loadLogs();
});

function loadLogs() {
    const params = new URLSearchParams({
        action: 'list',
        page: currentPage,
        limit: logsPerPage
    });
    
    fetch(`${BASE_PATH}/api/user-logs.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLogs(data.data);
                displayPagination(data.pagination);
                document.getElementById('logCount').textContent = data.pagination.total + ' records';
            } else {
                showError('Failed to load logs: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to load logs');
        });
}

function applyFilters() {
    const userId = document.getElementById('filterUser').value;
    const action = document.getElementById('filterAction').value;
    const entityType = document.getElementById('filterEntityType').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    
    const params = new URLSearchParams({
        action: 'search',
        page: 1,
        limit: logsPerPage
    });
    
    if (userId) params.append('user_id', userId);
    if (action) params.append('search_action', action);
    if (entityType) params.append('entity_type', entityType);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    
    fetch(`${BASE_PATH}/api/user-logs.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLogs(data.data);
                displayPagination(data.pagination);
                document.getElementById('logCount').textContent = data.pagination.total + ' records';
                currentPage = 1;
            } else {
                showError('Failed to search logs: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to search logs');
        });
}

function resetFilters() {
    document.getElementById('filterUser').value = '';
    document.getElementById('filterAction').value = '';
    document.getElementById('filterEntityType').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    currentPage = 1;
    loadLogs();
}

function displayLogs(logs) {
    const table = document.getElementById('logsTable');
    
    if (logs.length === 0) {
        table.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No logs found</td></tr>';
        return;
    }
    
    table.innerHTML = logs.map(log => `
        <tr>
            <td>
                <small class="text-muted">${new Date(log.created_at).toLocaleString()}</small>
            </td>
            <td>
                <small>
                    ${log.first_name && log.last_name ? htmlEscape(log.first_name + ' ' + log.last_name) : 'Unknown'}
                    <br><small class="text-muted">${log.email ? htmlEscape(log.email) : 'N/A'}</small>
                </small>
            </td>
            <td>
                <span class="badge bg-secondary">${htmlEscape(log.action)}</span>
            </td>
            <td>
                ${log.entity_type ? htmlEscape(log.entity_type) : '<small class="text-muted">-</small>'}
            </td>
            <td>
                ${log.entity_id ? log.entity_id : '<small class="text-muted">-</small>'}
            </td>
            <td>
                <small class="text-muted">${log.ip_address ? htmlEscape(log.ip_address) : 'N/A'}</small>
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
    
    const userId = document.getElementById('filterUser').value;
    const action = document.getElementById('filterAction').value;
    const entityType = document.getElementById('filterEntityType').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo = document.getElementById('filterDateTo').value;
    
    if (userId || action || entityType || dateFrom || dateTo) {
        applyFilters();
    } else {
        loadLogs();
    }
}

function showError(message) {
    document.getElementById('logsTable').innerHTML = `<tr><td colspan="6" class="text-danger text-center py-4">${htmlEscape(message)}</td></tr>`;
}

function htmlEscape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
