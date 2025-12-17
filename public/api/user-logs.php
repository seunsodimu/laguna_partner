<?php
/**
 * User Logs API
 * Fetch and filter user activity logs
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
if ($user['type'] !== 'user' || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin access required.']);
    exit;
}

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            listLogs($db);
            break;
        case 'search':
            searchLogs($db);
            break;
        case 'stats':
            getStats($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function listLogs($db) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 50);
    if ($limit > 500) $limit = 500;
    
    $offset = ($page - 1) * $limit;
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM user_logs");
    $total_count = $total['count'];
    
    $logs = $db->fetchAll(
        "SELECT ul.*, u.email, u.first_name, u.last_name 
         FROM user_logs ul 
         LEFT JOIN users u ON ul.user_id = u.id 
         ORDER BY ul.created_at DESC 
         LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'pages' => ceil($total_count / $limit)
        ]
    ]);
}

function searchLogs($db) {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $action = isset($_GET['search_action']) ? trim($_GET['search_action']) : null;
    $entity_type = isset($_GET['entity_type']) ? trim($_GET['entity_type']) : null;
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 50);
    if ($limit > 500) $limit = 500;
    
    $offset = ($page - 1) * $limit;
    
    $where = [];
    $params = [];
    
    if ($user_id) {
        $where[] = "ul.user_id = ?";
        $params[] = $user_id;
    }
    
    if ($action) {
        $where[] = "ul.action LIKE ?";
        $params[] = '%' . $action . '%';
    }
    
    if ($entity_type) {
        $where[] = "ul.entity_type = ?";
        $params[] = $entity_type;
    }
    
    if ($date_from) {
        $where[] = "DATE(ul.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where[] = "DATE(ul.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
    
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM user_logs ul $whereClause",
        $params
    );
    $total_count = $total['count'];
    
    $logs = $db->fetchAll(
        "SELECT ul.*, u.email, u.first_name, u.last_name 
         FROM user_logs ul 
         LEFT JOIN users u ON ul.user_id = u.id 
         $whereClause 
         ORDER BY ul.created_at DESC 
         LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'pages' => ceil($total_count / $limit)
        ]
    ]);
}

function getStats($db) {
    $stats = [
        'total_logs' => $db->fetchOne("SELECT COUNT(*) as count FROM user_logs")['count'],
        'unique_users' => $db->fetchOne("SELECT COUNT(DISTINCT user_id) as count FROM user_logs")['count'],
        'actions' => $db->fetchAll("SELECT action, COUNT(*) as count FROM user_logs GROUP BY action ORDER BY count DESC LIMIT 10"),
        'entity_types' => $db->fetchAll("SELECT entity_type, COUNT(*) as count FROM user_logs WHERE entity_type IS NOT NULL GROUP BY entity_type ORDER BY count DESC"),
        'today_logs' => $db->fetchOne("SELECT COUNT(*) as count FROM user_logs WHERE DATE(created_at) = CURDATE()")['count'],
        'this_month_logs' => $db->fetchOne("SELECT COUNT(*) as count FROM user_logs WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")['count']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}
