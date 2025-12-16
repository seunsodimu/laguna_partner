<?php
/**
 * Items API Endpoint
 * Handles item listing and notification management for dealers
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

header('Content-Type: application/json');

// Start session and check authentication
session_start();
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$userType = $_SESSION['user_type'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
$accountId = $_SESSION['active_account_id'] ?? null;

// Only dealers can access this endpoint
if ($userType !== 'dealer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Dealer access only.']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $userId, $accountId);
            break;
        case 'POST':
            handlePost($db, $userId, $accountId);
            break;
        case 'DELETE':
            handleDelete($db, $userId, $accountId);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($db, $userId, $accountId) {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'notifications') {
        // Get dealer's notification subscriptions
        $notifications = $db->fetchAll(
            "SELECT n.*, i.name as item_name, i.item_id as sku, i.quantity as quantity_available
             FROM item_notifications n
             INNER JOIN items i ON n.item_id = i.id
             WHERE n.user_id = ? AND n.is_active = 1
             ORDER BY n.created_at DESC",
            [$userId]
        );
        
        echo json_encode(['success' => true, 'data' => $notifications]);
        return;
    }
    
    // List items with search and pagination
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 50;
    $offset = ($page - 1) * $perPage;
    
    $filters = [];
    $params = [];
    
    if (!empty($search)) {
        $filters[] = "(i.name LIKE ? OR i.item_id LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM items i {$whereClause}";
    $totalResult = $db->fetchOne($countSql, $params);
    $total = $totalResult['total'];
    
    // Get items with notification status
    $sql = "SELECT i.id, i.name as item_name, i.item_id as sku, i.quantity as quantity_available, 
                   i.description, i.price, i.category, i.is_active,
                   (SELECT COUNT(*) FROM item_notifications 
                    WHERE item_id = i.id AND user_id = ? AND is_active = 1) as has_notification,
                   (SELECT notification_type FROM item_notifications 
                    WHERE item_id = i.id AND user_id = ? AND is_active = 1 LIMIT 1) as notification_type,
                   (SELECT threshold FROM item_notifications 
                    WHERE item_id = i.id AND user_id = ? AND is_active = 1 LIMIT 1) as threshold_quantity
            FROM items i
            {$whereClause}
            ORDER BY i.name
            LIMIT ? OFFSET ?";
    
    $queryParams = array_merge([$userId, $userId, $userId], $params, [$perPage, $offset]);
    $items = $db->fetchAll($sql, $queryParams);
    
    echo json_encode([
        'success' => true,
        'data' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ]);
}

function handlePost($db, $userId, $accountId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'subscribe') {
        $itemId = $data['item_id'] ?? null;
        $notificationType = $data['notification_type'] ?? null;
        $thresholdQuantity = $data['threshold_quantity'] ?? null;
        
        if (!$itemId || !$notificationType) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Item ID and notification type required']);
            return;
        }
        
        // Validate notification type
        $validTypes = ['in_stock', 'out_of_stock', 'low_stock'];
        if (!in_array($notificationType, $validTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid notification type']);
            return;
        }
        
        // Validate threshold for low_stock
        if ($notificationType === 'low_stock' && (!$thresholdQuantity || $thresholdQuantity < 1)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Threshold quantity required for low stock notifications']);
            return;
        }
        
        // Check if item exists
        $item = $db->fetchOne("SELECT * FROM items WHERE id = ?", [$itemId]);
        if (!$item) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            return;
        }
        
        // Check if notification already exists
        $existing = $db->fetchOne(
            "SELECT * FROM item_notifications WHERE item_id = ? AND user_id = ? AND is_active = 1",
            [$itemId, $userId]
        );
        
        if ($existing) {
            // Update existing notification
            $db->query(
                "UPDATE item_notifications 
                 SET notification_type = ?, threshold = ?
                 WHERE id = ?",
                [$notificationType, $thresholdQuantity, $existing['id']]
            );
            
            $notificationId = $existing['id'];
            $message = 'Notification updated successfully';
        } else {
            // Create new notification
            $db->query(
                "INSERT INTO item_notifications (item_id, user_id, notification_type, threshold, is_active, created_at)
                 VALUES (?, ?, ?, ?, 1, NOW())",
                [$itemId, $userId, $notificationType, $thresholdQuantity]
            );
            
            $notificationId = $db->lastInsertId();
            $message = 'Notification created successfully';
        }
        
        // Get the notification with item info
        $notification = $db->fetchOne(
            "SELECT n.*, i.name as item_name, i.item_id as sku, i.quantity as quantity_available
             FROM item_notifications n
             INNER JOIN items i ON n.item_id = i.id
             WHERE n.id = ?",
            [$notificationId]
        );
        
        // Log activity
        Auth::logActivity($userId, 'subscribe_item_notification', 'item_notification', [
            'item_id' => $itemId,
            'notification_type' => $notificationType,
            'item_sku' => $item['item_id']
        ]);
        
        echo json_encode(['success' => true, 'message' => $message, 'data' => $notification]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handleDelete($db, $userId, $accountId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $notificationId = $data['id'] ?? null;
    
    if (!$notificationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        return;
    }
    
    // Verify notification belongs to this dealer
    $notification = $db->fetchOne(
        "SELECT * FROM item_notifications WHERE id = ? AND user_id = ?",
        [$notificationId, $userId]
    );
    
    if (!$notification) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
        return;
    }
    
    // Soft delete (deactivate)
    $db->query(
        "UPDATE item_notifications SET is_active = 0, updated_at = NOW() WHERE id = ?",
        [$notificationId]
    );
    
    // Log activity
    Auth::logActivity($userId, 'unsubscribe_item_notification', 'item_notification', [
        'notification_id' => $notificationId
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
}