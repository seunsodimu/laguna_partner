<?php
/**
 * Purchase Orders API Endpoint
 * Handles CRUD operations for purchase orders
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/EmailService.php';
require_once __DIR__ . '/../../src/NetSuiteClient.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;
use LagunaPartners\EmailService;
use LagunaPartners\NetSuiteClient;

header('Content-Type: application/json');

// Start session and check authentication
session_start();
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$userType = $_SESSION['user_type'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $userType, $userId);
            break;
        case 'POST':
            handlePost($db, $userType, $userId);
            break;
        case 'PUT':
            handlePut($db, $userType, $userId);
            break;
        case 'DELETE':
            handleDelete($db, $userType, $userId);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($db, $userType, $userId) {
    $poId = $_GET['id'] ?? null;
    $accountId = $_GET['account_id'] ?? $_SESSION['current_account_id'] ?? null;
    
    if ($poId) {
        // Get single PO with details
        $po = $db->fetchOne(
            "SELECT po.*, a.company_name, a.category as vendor_category,
                    u.name as buyer_name, u.email as buyer_email
             FROM purchase_orders po
             LEFT JOIN accounts a ON po.vendor_id = a.id
             LEFT JOIN users u ON po.assigned_buyer_id = u.id
             WHERE po.id = ?",
            [$poId]
        );
        
        if (!$po) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
            return;
        }
        
        // Check access permissions
        if ($userType === 'vendor' && $po['vendor_id'] != $accountId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        // Get PO items
        $items = $db->fetchAll(
            "SELECT * FROM po_items WHERE po_id = ? ORDER BY line_number",
            [$poId]
        );
        
        // Get PO comments
        $comments = $db->fetchAll(
            "SELECT c.*, u.name as user_name, u.email as user_email
             FROM po_comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.po_id = ?
             ORDER BY c.created_at DESC",
            [$poId]
        );
        
        // Get PO documents
        $documents = $db->fetchAll(
            "SELECT d.*, u.name as uploaded_by_name
             FROM po_documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             WHERE d.po_id = ?
             ORDER BY d.uploaded_at DESC",
            [$poId]
        );
        
        $po['items'] = $items;
        $po['comments'] = $comments;
        $po['documents'] = $documents;
        
        echo json_encode(['success' => true, 'data' => $po]);
        
    } else {
        // Get list of POs with filters
        $filters = [];
        $params = [];
        
        // Vendor can only see their own POs
        if ($userType === 'vendor' && $accountId) {
            $filters[] = "po.vendor_id = ?";
            $params[] = $accountId;
            // Vendors only see open POs
            $filters[] = "po.status IN ('B', 'E')";
        }
        
        // Apply additional filters
        if (!empty($_GET['status'])) {
            $filters[] = "po.status = ?";
            $params[] = $_GET['status'];
        }
        
        if (!empty($_GET['vendor_id'])) {
            $filters[] = "po.vendor_id = ?";
            $params[] = $_GET['vendor_id'];
        }
        
        if (!empty($_GET['buyer_id'])) {
            $filters[] = "po.assigned_buyer_id = ?";
            $params[] = $_GET['buyer_id'];
        }
        
        if (!empty($_GET['has_updates'])) {
            $filters[] = "po.has_vendor_updates = ?";
            $params[] = $_GET['has_updates'] === 'true' ? 1 : 0;
        }
        
        if (!empty($_GET['search'])) {
            $filters[] = "(po.tranid LIKE ? OR a.company_name LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
        
        $sql = "SELECT po.*, a.company_name, a.category as vendor_category,
                       u.name as buyer_name,
                       (SELECT COUNT(*) FROM po_items WHERE po_id = po.id) as item_count
                FROM purchase_orders po
                LEFT JOIN accounts a ON po.vendor_id = a.id
                LEFT JOIN users u ON po.assigned_buyer_id = u.id
                {$whereClause}
                ORDER BY po.created_date DESC";
        
        $pos = $db->fetchAll($sql, $params);
        
        echo json_encode(['success' => true, 'data' => $pos]);
    }
}

function handlePost($db, $userType, $userId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'add_comment':
            addComment($db, $userId, $data);
            break;
        case 'upload_document':
            // Document upload is handled separately via multipart/form-data
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Use upload endpoint for documents']);
            break;
        case 'approve_changes':
            approveChanges($db, $userType, $userId, $data);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePut($db, $userType, $userId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $poId = $data['id'] ?? null;
    
    if (!$poId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID required']);
        return;
    }
    
    // Get current PO
    $po = $db->fetchOne("SELECT * FROM purchase_orders WHERE id = ?", [$poId]);
    
    if (!$po) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        return;
    }
    
    // Check permissions
    if ($userType === 'vendor') {
        $accountId = $_SESSION['current_account_id'] ?? null;
        if ($po['vendor_id'] != $accountId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        // Vendors can only edit dates when status is B or E
        if (!in_array($po['status'], ['B', 'E'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot edit PO in current status']);
            return;
        }
    }
    
    // Track changes for notification
    $changes = [];
    $updateFields = [];
    $updateParams = [];
    
    // Fields that can be updated
    $editableFields = ['port_date', 'estimated_delivery_date', 'ship_date'];
    
    // Buyers and admins can edit more fields
    if (in_array($userType, ['buyer', 'admin'])) {
        $editableFields = array_merge($editableFields, ['status', 'assigned_buyer_id', 'memo']);
    }
    
    foreach ($editableFields as $field) {
        if (isset($data[$field]) && $data[$field] !== $po[$field]) {
            $changes[$field] = [
                'old' => $po[$field],
                'new' => $data[$field]
            ];
            $updateFields[] = "{$field} = ?";
            $updateParams[] = $data[$field];
        }
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => true, 'message' => 'No changes detected']);
        return;
    }
    
    // Add metadata fields
    if ($userType === 'vendor') {
        $updateFields[] = "has_vendor_updates = 1";
        $updateFields[] = "is_synced_to_netsuite = 0";
        $updateFields[] = "last_vendor_update = NOW()";
    }
    
    $updateFields[] = "updated_at = NOW()";
    $updateParams[] = $poId;
    
    $sql = "UPDATE purchase_orders SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $db->query($sql, $updateParams);
    
    // Log the change
    Auth::logActivity($userId, 'update_po', "Updated PO #{$po['tranid']}", [
        'po_id' => $poId,
        'changes' => $changes
    ]);
    
    // Send notification email if vendor made changes
    if ($userType === 'vendor' && $po['assigned_buyer_id']) {
        $emailService = new EmailService();
        $buyer = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$po['assigned_buyer_id']]);
        
        if ($buyer) {
            $emailService->sendPOUpdateNotification(
                $buyer['email'],
                $po['tranid'],
                $changes,
                $userType
            );
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Purchase order updated successfully', 'changes' => $changes]);
}

function handleDelete($db, $userType, $userId) {
    // Only admins can delete
    if ($userType !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    $type = $data['type'] ?? 'comment';
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    if ($type === 'comment') {
        $db->query("DELETE FROM po_comments WHERE id = ?", [$id]);
        echo json_encode(['success' => true, 'message' => 'Comment deleted']);
    } elseif ($type === 'document') {
        $doc = $db->fetchOne("SELECT * FROM po_documents WHERE id = ?", [$id]);
        if ($doc && file_exists($doc['file_path'])) {
            unlink($doc['file_path']);
        }
        $db->query("DELETE FROM po_documents WHERE id = ?", [$id]);
        echo json_encode(['success' => true, 'message' => 'Document deleted']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
    }
}

function addComment($db, $userId, $data) {
    $poId = $data['po_id'] ?? null;
    $comment = $data['comment'] ?? '';
    
    if (!$poId || empty($comment)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID and comment required']);
        return;
    }
    
    $db->query(
        "INSERT INTO po_comments (po_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())",
        [$poId, $userId, $comment]
    );
    
    $commentId = $db->lastInsertId();
    
    // Get the comment with user info
    $newComment = $db->fetchOne(
        "SELECT c.*, u.name as user_name, u.email as user_email
         FROM po_comments c
         LEFT JOIN users u ON c.user_id = u.id
         WHERE c.id = ?",
        [$commentId]
    );
    
    echo json_encode(['success' => true, 'message' => 'Comment added', 'data' => $newComment]);
}

function approveChanges($db, $userType, $userId, $data) {
    if (!in_array($userType, ['buyer', 'admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only buyers can approve changes']);
        return;
    }
    
    $poId = $data['po_id'] ?? null;
    
    if (!$poId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID required']);
        return;
    }
    
    $po = $db->fetchOne("SELECT * FROM purchase_orders WHERE id = ?", [$poId]);
    
    if (!$po) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        return;
    }
    
    if (!$po['has_vendor_updates']) {
        echo json_encode(['success' => false, 'message' => 'No pending vendor updates']);
        return;
    }
    
    try {
        // Sync to NetSuite
        $config = require __DIR__ . '/../../config/config.php';
        $nsClient = new NetSuiteClient(
            $config['netsuite']['account_id'],
            $config['netsuite']['consumer_key'],
            $config['netsuite']['consumer_secret'],
            $config['netsuite']['token_id'],
            $config['netsuite']['token_secret']
        );
        
        // Update PO in NetSuite
        $updateData = [];
        if ($po['port_date']) $updateData['custbody_port_date'] = $po['port_date'];
        if ($po['estimated_delivery_date']) $updateData['custcol_est_delivery_date'] = $po['estimated_delivery_date'];
        if ($po['ship_date']) $updateData['shipDate'] = $po['ship_date'];
        
        $nsClient->updateRecord('purchaseOrder', $po['netsuite_id'], $updateData);
        
        // Mark as synced
        $db->query(
            "UPDATE purchase_orders 
             SET has_vendor_updates = 0, is_synced_to_netsuite = 1, last_synced_at = NOW()
             WHERE id = ?",
            [$poId]
        );
        
        // Send confirmation email to vendor
        $emailService = new EmailService();
        $vendor = $db->fetchOne(
            "SELECT u.* FROM users u
             INNER JOIN user_accounts ua ON u.id = ua.user_id
             WHERE ua.account_id = ? LIMIT 1",
            [$po['vendor_id']]
        );
        
        if ($vendor) {
            $emailService->sendTemplate(
                $vendor['email'],
                'buyer_approved_changes',
                [
                    'po_number' => $po['tranid'],
                    'buyer_name' => $_SESSION['user_name'] ?? 'Buyer'
                ]
            );
        }
        
        Auth::logActivity($userId, 'approve_po_changes', "Approved changes for PO #{$po['tranid']}", ['po_id' => $poId]);
        
        echo json_encode(['success' => true, 'message' => 'Changes approved and synced to NetSuite']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to sync to NetSuite: ' . $e->getMessage()]);
    }
}