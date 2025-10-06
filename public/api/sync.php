<?php
/**
 * Sync API Endpoint
 * Handles manual sync triggers from admin dashboard
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/SyncService.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;
use LagunaPartners\SyncService;

header('Content-Type: application/json');

// Start session and check authentication
session_start();
if (!Auth::check()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

$user = Auth::user();
if ($user['type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? '';

if (empty($type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Sync type required']);
    exit;
}

try {
    $config = require __DIR__ . '/../../config/config.php';
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
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($type) . ' sync completed successfully',
        'records' => $result['records_processed'] ?? 0,
        'details' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}