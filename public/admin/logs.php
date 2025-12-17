<?php
/**
 * Logs Directory Viewer
 * View and manage log files
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Auth.php';

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

$pageTitle = 'System Logs';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>System Logs</h2>
            <p class="text-muted">View and manage system log files</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Log Files</h5>
                </div>
                <div style="max-height: 600px; overflow-y: auto;">
                    <div class="list-group list-group-flush" id="logFilesList">
                        <div class="text-center py-4 text-muted">
                            <span class="spinner-border spinner-border-sm"></span> Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" id="logFileTitle">Select a log file</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" id="downloadBtn" onclick="downloadCurrentLog()" style="display: none;">
                            <i class="bi bi-download"></i> Download
                        </button>
                        <button class="btn btn-sm btn-outline-danger" id="deleteBtn" onclick="deleteCurrentLog()" style="display: none;">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="logContent" style="max-height: 600px; overflow-y: auto; background-color: #f5f5f5; padding: 15px; border-radius: 4px;">
                        <div class="text-center text-muted py-5">
                            <p><i class="bi bi-file-earmark-text" style="font-size: 2rem; opacity: 0.5;"></i></p>
                            <p>Select a log file from the list to view its contents</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1050;"></div>

<script>
const BASE_PATH = '<?php echo BASE_PATH; ?>';
let currentLogFile = null;

document.addEventListener('DOMContentLoaded', function() {
    loadLogFiles();
});

function loadLogFiles() {
    fetch(`${BASE_PATH}/api/logs-directory.php?action=list`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLogFiles(data.data);
            } else {
                showToast('Error: ' + (data.error || 'Failed to load logs'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load logs', 'error');
        });
}

function displayLogFiles(files) {
    const list = document.getElementById('logFilesList');
    
    if (files.length === 0) {
        list.innerHTML = '<div class="text-center text-muted py-4">No log files found</div>';
        return;
    }
    
    list.innerHTML = files.map(file => `
        <a href="#" class="list-group-item list-group-item-action" onclick="viewLogFile('${htmlEscape(file.name)}'); return false;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong>${htmlEscape(file.name)}</strong>
                    <br><small class="text-muted">${formatFileSize(file.size)}</small>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block">${file.modified_formatted}</small>
                </div>
            </div>
        </a>
    `).join('');
}

function viewLogFile(filename) {
    currentLogFile = filename;
    
    document.getElementById('logFileTitle').textContent = filename;
    document.getElementById('downloadBtn').style.display = 'inline-block';
    document.getElementById('deleteBtn').style.display = 'inline-block';
    
    const contentDiv = document.getElementById('logContent');
    contentDiv.innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm"></span> Loading...</div>';
    
    fetch(`${BASE_PATH}/api/logs-directory.php?action=read&filename=${encodeURIComponent(filename)}&lines=200`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const lines = data.data.content.split('\n');
                const formattedContent = lines.map(line => htmlEscape(line)).join('<br>');
                contentDiv.innerHTML = `<pre style="margin: 0; font-family: monospace; font-size: 0.85rem;">${formattedContent}</pre>`;
            } else {
                showToast('Error: ' + (data.error || 'Failed to read log'), 'error');
                contentDiv.innerHTML = '<div class="text-danger">Failed to load log file</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to read log', 'error');
            contentDiv.innerHTML = '<div class="text-danger">Failed to load log file</div>';
        });
}

function downloadCurrentLog() {
    if (!currentLogFile) return;
    
    const link = document.createElement('a');
    link.href = `${BASE_PATH}/api/logs-directory.php?action=download&filename=${encodeURIComponent(currentLogFile)}`;
    link.download = currentLogFile;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function deleteCurrentLog() {
    if (!currentLogFile) return;
    
    if (!confirm(`Are you sure you want to delete "${currentLogFile}"? This action cannot be undone.`)) {
        return;
    }
    
    fetch(`${BASE_PATH}/api/logs-directory.php?action=delete&filename=${encodeURIComponent(currentLogFile)}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Log file deleted successfully', 'success');
            currentLogFile = null;
            document.getElementById('logFileTitle').textContent = 'Select a log file';
            document.getElementById('downloadBtn').style.display = 'none';
            document.getElementById('deleteBtn').style.display = 'none';
            document.getElementById('logContent').innerHTML = `
                <div class="text-center text-muted py-5">
                    <p><i class="bi bi-file-earmark-text" style="font-size: 2rem; opacity: 0.5;"></i></p>
                    <p>Select a log file from the list to view its contents</p>
                </div>
            `;
            setTimeout(() => loadLogFiles(), 1000);
        } else {
            showToast('Error: ' + (data.error || 'Failed to delete log'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to delete log', 'error');
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function showToast(message, type = 'info') {
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${htmlEscape(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
}

function htmlEscape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
