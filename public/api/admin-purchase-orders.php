<?php
/**
 * Admin Purchase Orders API
 * Fetch and filter purchase orders for admin datatable
 */

require_once '../../config/config.php';
require_once '../../src/Database.php';
require_once '../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

header('Content-Type: application/json');

session_start();

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Auth::user();
if ($user['type'] !== 'user' || !in_array($user['role'], ['buyer', 'accounting', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

function getStatusMap() {
    return [
        'A' => 'Pending Approval',
        'B' => 'Pending Received',
        'E' => 'Partially Received',
        'F' => 'Pending Billing/Partially Received',
        'H' => 'Pending Billing'
    ];
}

function getStatusDescription($code) {
    $map = getStatusMap();
    return $map[$code] ?? $code;
}

try {
    switch ($action) {
        case 'list':
            listPurchaseOrders($db, $user);
            break;
        case 'search':
            searchPurchaseOrders($db, $user);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function listPurchaseOrders($db, $user) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 50);
    if ($limit > 500) $limit = 500;
    
    $offset = ($page - 1) * $limit;
    
    $baseQuery = "FROM purchase_orders WHERE 1=1";
    
    if ($user['role'] !== 'admin') {
        $baseQuery .= " AND buyer_id = " . intval($user['id']);
    }
    
    $total = $db->fetchOne("SELECT COUNT(*) as count " . $baseQuery);
    $total_count = $total['count'];
    
    $pos = $db->fetchAll(
        "SELECT id, tran_id, vendor_name, total_amount, currency, status, created_date, due_date, rejection_reason " .
        $baseQuery .
        " ORDER BY created_date DESC LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
    
    foreach ($pos as &$po) {
        $po['status_description'] = getStatusDescription($po['status']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $pos,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'pages' => ceil($total_count / $limit)
        ]
    ]);
}

function searchPurchaseOrders($db, $user) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $vendor = isset($_GET['vendor']) ? trim($_GET['vendor']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 50);
    if ($limit > 500) $limit = 500;
    
    $offset = ($page - 1) * $limit;
    
    $where = [];
    $params = [];
    
    if ($user['role'] !== 'admin') {
        $where[] = "buyer_id = ?";
        $params[] = $user['id'];
    }
    
    if ($search) {
        $where[] = "(tran_id LIKE ? OR vendor_name LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    
    if ($vendor) {
        $where[] = "vendor_name LIKE ?";
        $params[] = '%' . $vendor . '%';
    }
    
    if ($status) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    
    if ($date_from) {
        $where[] = "DATE(created_date) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where[] = "DATE(created_date) <= ?";
        $params[] = $date_to;
    }
    
    $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
    
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM purchase_orders WHERE " . $whereClause,
        $params
    );
    $total_count = $total['count'];
    
    $pos = $db->fetchAll(
        "SELECT id, tran_id, vendor_name, total_amount, currency, status, created_date, due_date, rejection_reason " .
        "FROM purchase_orders WHERE " . $whereClause .
        " ORDER BY created_date DESC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    
    foreach ($pos as &$po) {
        $po['status_description'] = getStatusDescription($po['status']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $pos,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'pages' => ceil($total_count / $limit)
        ]
    ]);
}
