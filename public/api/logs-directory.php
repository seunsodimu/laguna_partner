<?php
/**
 * Logs Directory API
 * Fetch and display log files from the logs directory
 */

require_once '../../config/config.php';
require_once '../../src/Auth.php';

use LagunaPartners\Auth;

header('Content-Type: application/json');

session_start();

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Auth::user();
if ($user['type'] !== 'user' || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin access required.']);
    exit;
}

$action = $_GET['action'] ?? '';
$logsDir = __DIR__ . '/../../logs';

try {
    switch ($action) {
        case 'list':
            listLogFiles();
            break;
        case 'read':
            readLogFile();
            break;
        case 'download':
            downloadLogFile();
            break;
        case 'delete':
            deleteLogFile();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function listLogFiles() {
    global $logsDir;
    
    if (!is_dir($logsDir)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'Logs directory does not exist'
        ]);
        return;
    }
    
    $files = [];
    $scanned = scandir($logsDir, SCANDIR_SORT_DESCENDING);
    
    foreach ($scanned as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $logsDir . '/' . $file;
        
        if (is_file($filePath) && strpos($file, '.log') !== false) {
            $files[] = [
                'name' => $file,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath),
                'modified_formatted' => date('M d, Y H:i:s', filemtime($filePath))
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $files
    ]);
}

function readLogFile() {
    global $logsDir;
    
    $filename = basename($_GET['filename'] ?? '');
    if (!$filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Filename required']);
        return;
    }
    
    $filePath = $logsDir . '/' . $filename;
    
    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    if (strpos($filename, '.log') === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type']);
        return;
    }
    
    $lines = intval($_GET['lines'] ?? 100);
    if ($lines > 1000) $lines = 1000;
    if ($lines < 1) $lines = 1;
    
    $content = '';
    $handle = fopen($filePath, 'r');
    
    if ($handle) {
        $file_lines = [];
        while (($line = fgets($handle)) !== false) {
            $file_lines[] = $line;
        }
        fclose($handle);
        
        $display_lines = array_slice($file_lines, -$lines);
        $content = implode('', $display_lines);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'filename' => $filename,
            'size' => filesize($filePath),
            'modified' => date('M d, Y H:i:s', filemtime($filePath)),
            'content' => $content,
            'lines_shown' => min($lines, count($file_lines) ?? 0)
        ]
    ]);
}

function downloadLogFile() {
    global $logsDir;
    
    $filename = basename($_GET['filename'] ?? '');
    if (!$filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Filename required']);
        return;
    }
    
    $filePath = $logsDir . '/' . $filename;
    
    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    if (strpos($filename, '.log') === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type']);
        return;
    }
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

function deleteLogFile() {
    global $logsDir;
    
    $filename = basename($_GET['filename'] ?? '');
    if (!$filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Filename required']);
        return;
    }
    
    $filePath = $logsDir . '/' . $filename;
    
    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    if (strpos($filename, '.log') === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type']);
        return;
    }
    
    if (unlink($filePath)) {
        echo json_encode([
            'success' => true,
            'message' => 'Log file deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete log file']);
    }
}
