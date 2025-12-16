<?php
/**
 * Sync API Endpoint
 * Handles manual sync triggers from admin dashboard
 */

// Suppress ALL errors from displaying (we'll handle them as JSON)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Increase execution time for sync operations (10 minutes)
set_time_limit(600);
ini_set('max_execution_time', '600');

// Increase memory limit
ini_set('memory_limit', '512M');

// Start output buffering FIRST
ob_start();

// Set JSON header to ensure proper response format
header('Content-Type: application/json');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal Error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => 'fatal'
        ]);
    }
});

// Set error handler to catch any errors and return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $errstr,
        'file' => $errfile,
        'line' => $errline,
        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
    ]);
    exit;
});

// Set exception handler to catch any uncaught exceptions
set_exception_handler(function($exception) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    exit;
});

try {
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../src/Database.php';
    require_once __DIR__ . '/../../src/Auth.php';
    require_once __DIR__ . '/../../src/NetSuiteClient.php';
    require_once __DIR__ . '/../../src/SyncService.php';
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load required files: ' . $e->getMessage()
    ]);
    exit;
}

use LagunaPartners\Database;
use LagunaPartners\Auth;
use LagunaPartners\SyncService;

// Start session and check authentication
session_start();
if (!Auth::check()) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

$user = Auth::user();
if ($user['type'] !== 'user' || $user['role'] !== 'admin') {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? '';

if (empty($type)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Sync type required']);
    exit;
}

try {
    // Load configuration
    $configPath = __DIR__ . '/../../config/config.php';
    if (!file_exists($configPath)) {
        throw new Exception('Configuration file not found: ' . $configPath);
    }
    $config = require $configPath;
    
    // Initialize sync service
    $syncService = new SyncService($config);
    
    $result = null;
    
    switch ($type) {
        case 'accounts':
            $result = $syncService->syncAccounts();
            break;
        case 'purchase-orders':
            $result = $syncService->syncPurchaseOrders();
            break;
        case 'items':
            $result = $syncService->syncItems();
            break;
        default:
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid sync type']);
            exit;
    }
    
    // Log the admin action
    Auth::logActivity(
        $_SESSION['user_id'],
        'manual_sync',
        'sync',
        ['sync_type' => $type, 'result' => $result]
    );
    
    // Clean output buffer and send response
    ob_clean();
    
    // Build detailed message
    $message = ucfirst($type) . ' sync completed successfully';
    if (isset($result['stats'])) {
        $stats = $result['stats'];
        $details = [];
        if (isset($stats['created']) && $stats['created'] > 0) {
            $details[] = "{$stats['created']} created";
        }
        if (isset($stats['updated']) && $stats['updated'] > 0) {
            $details[] = "{$stats['updated']} updated";
        }
        if (isset($stats['failed']) && $stats['failed'] > 0) {
            $details[] = "{$stats['failed']} failed";
        }
        if (isset($stats['skipped']) && $stats['skipped'] > 0) {
            $details[] = "{$stats['skipped']} skipped (will sync next time)";
        }
        if (!empty($details)) {
            $message .= ': ' . implode(', ', $details);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'records' => $result['records_processed'] ?? ($result['stats']['processed'] ?? 0),
        'details' => $result
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}