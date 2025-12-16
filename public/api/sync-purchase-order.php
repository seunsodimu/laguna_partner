<?php
/**
 * Sync Purchase Order Endpoint
 * Allows NetSuite to sync individual purchase orders to the partner portal
 * 
 * Supports two authentication methods:
 * 1. User session (for authenticated users)
 * 2. NetSuite API key (for server-to-server calls from NetSuite)
 * 
 * Usage:
 * POST /api/sync-purchase-order.php?id=12345
 * With header: Authorization: Bearer {API_KEY} OR Cookie: LAGUNA_SESSION={session}
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
set_time_limit(300);
ini_set('memory_limit', '256M');

ob_start();
header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal Error: ' . $error['message'],
            'type' => 'fatal'
        ]);
    }
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'PHP Error: ' . $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit;
});

set_exception_handler(function($exception) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
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
use LagunaPartners\NetSuiteClient;
use LagunaPartners\SyncService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$po_id = $_GET['id'] ?? null;
if (!$po_id) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Purchase order ID required']);
    exit;
}

$po_id = intval($po_id);
if ($po_id <= 0) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid purchase order ID']);
    exit;
}

try {
    $config = require __DIR__ . '/../../config/config.php';
    
    $authUser = null;
    $authMethod = null;
    
    session_start();
    
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $apiKey = $matches[1];
        
        $expectedKey = $_ENV['NETSUITE_WEBHOOK_API_KEY'] ?? null;
        
        if (!$expectedKey) {
            throw new Exception('NetSuite webhook API key not configured');
        }
        
        if (!hash_equals($expectedKey, $apiKey)) {
            ob_clean();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid API key']);
            exit;
        }
        
        $authMethod = 'api_key';
        $authUser = ['type' => 'system', 'id' => null, 'name' => 'NetSuite Webhook'];
        
    } elseif (Auth::check()) {
        $authMethod = 'session';
        $authUser = Auth::user();
        
        if ($authUser['type'] === 'vendor') {
            ob_clean();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Vendors cannot sync purchase orders']);
            exit;
        }
        
    } else {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $syncService = new SyncService($config);
    
    error_log("Starting PO sync for PO ID: $po_id via $authMethod");
    
    $result = $syncService->syncSinglePurchaseOrder($po_id);
    
    if ($authUser['id']) {
        Auth::logActivity(
            $authUser['id'],
            'sync_purchase_order',
            'purchase_order',
            ['po_id' => $po_id, 'auth_method' => $authMethod]
        );
    }
    
    ob_clean();
    http_response_code(200);
    echo json_encode(array_merge($result, ['auth_method' => $authMethod]));
    
} catch (Exception $e) {
    try {
        $db = Database::getInstance();
        $db->rollback();
    } catch (Exception $rollbackError) {
        error_log("Rollback error: " . $rollbackError->getMessage());
    }
    
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sync failed: ' . $e->getMessage(),
        'po_id' => $po_id ?? null
    ]);
    error_log("PO sync error for ID $po_id: " . $e->getMessage());
}
?>