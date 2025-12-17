<?php
/**
 * Admin Invoices API
 * Fetch and filter invoices for admin datatable
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

try {
    switch ($action) {
        case 'list':
            listInvoices($db, $user);
            break;
        case 'search':
            searchInvoices($db, $user);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function listInvoices($db, $user) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 50);
    if ($limit > 500) $limit = 500;
    
    $offset = ($page - 1) * $limit;
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM invoices");
    $total_count = $total['count'];
    
    $invoices = $db->fetchAll(
        "SELECT id, invoice_number, vendor_name, amount_total, currency, status, invoice_date, due_date " .
        "FROM invoices " .
        "ORDER BY invoice_date DESC LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
    
    echo json_encode([
        'success' => true,
        'data' => $invoices,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'pages' => ceil($total_count / $limit)
        ]
    ]);
}

function searchInvoices($db, $user) {
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
    
    if ($search) {
        $where[] = "(invoice_number LIKE ? OR vendor_name LIKE ?)";
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
        $where[] = "DATE(invoice_date) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where[] = "DATE(invoice_date) <= ?";
        $params[] = $date_to;
    }
    
    $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
    
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM invoices WHERE " . $whereClause,
        $params
    );
    $total_count = $total['count'];
    
    $invoices = $db->fetchAll(
        "SELECT id, invoice_number, vendor_name, amount_total, currency, status, invoice_date, due_date " .
        "FROM invoices WHERE " . $whereClause .
        " ORDER BY invoice_date DESC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    
    echo json_encode([
        'success' => true,
        'data' => $invoices,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'pages' => ceil($total_count / $limit)
        ]
    ]);
}
