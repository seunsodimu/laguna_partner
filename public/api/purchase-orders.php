<?php
/**
 * Purchase Orders API Endpoint
 * Handles CRUD operations for purchase orders
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/EmailService.php';
require_once __DIR__ . '/../../src/TeamsService.php';
require_once __DIR__ . '/../../src/NetSuiteClient.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;
use LagunaPartners\EmailService;
use LagunaPartners\TeamsService;
use LagunaPartners\NetSuiteClient;

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
    $accountId = $_GET['account_id'] ?? $_SESSION['active_account_id'] ?? null;
    
    if ($poId) {
        // Get single PO with details (includes new vessel and factory date fields)
        $po = $db->fetchOne(
            "SELECT po.*, a.company_name, a.type as vendor_type,
                    CONCAT(u.first_name, ' ', u.last_name) as buyer_name, u.email as buyer_email
             FROM purchase_orders po
             LEFT JOIN accounts a ON po.vendor_id = a.id
             LEFT JOIN users u ON po.buyer_id = u.id
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
            "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email as user_email
             FROM po_comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.po_id = ?
             ORDER BY c.created_at DESC",
            [$poId]
        );
        
        // Get PO documents
        $documents = $db->fetchAll(
            "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
             FROM po_documents d
             LEFT JOIN users u ON d.user_id = u.id
             WHERE d.po_id = ?
             ORDER BY d.created_at DESC",
            [$poId]
        );
        
        $po['items'] = $items;
        $po['comments'] = $comments;
        $po['documents'] = $documents;
        
        echo json_encode(['success' => true, 'data' => $po]);
        
    } else {
        if (isset($_GET['for_invoice']) && $_GET['for_invoice'] === '1') {
            if ($userType !== 'vendor') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }

            if (!$accountId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Vendor account not found']);
                return;
            }

            $pos = $db->fetchAll(
                "SELECT id, tran_id, total_amount FROM purchase_orders WHERE vendor_id = ? ORDER BY created_date DESC",
                [$accountId]
            );

            echo json_encode(['success' => true, 'data' => $pos]);
            return;
        }

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
            $filters[] = "po.buyer_id = ?";
            $params[] = $_GET['buyer_id'];
        }
        
        if (!empty($_GET['has_updates'])) {
            $filters[] = "po.has_vendor_updates = ?";
            $params[] = $_GET['has_updates'] === 'true' ? 1 : 0;
        }
        
        if (!empty($_GET['search'])) {
            $filters[] = "(po.tran_id LIKE ? OR a.company_name LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';
        
        $sql = "SELECT po.*, a.company_name, a.category as vendor_category,
                       CONCAT(u.first_name, ' ', u.last_name) as buyer_name,
                       (SELECT COUNT(*) FROM po_items WHERE po_id = po.id) as item_count
                FROM purchase_orders po
                LEFT JOIN accounts a ON po.vendor_id = a.id
                LEFT JOIN users u ON po.buyer_id = u.id
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
        case 'update_vendor_quantities':
            updateVendorQuantities($db, $userType, $userId, $data);
            break;
        case 'reject_po':
            rejectPO($db, $userType, $userId, $data);
            break;
        case 'accept_po':
            acceptPO($db, $userType, $userId, $data);
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
    
    // Get current PO with vendor and buyer info
    $po = $db->fetchOne(
        "SELECT po.*, a.company_name as vendor_name, a.category as vendor_category,
                CONCAT(u.first_name, ' ', u.last_name) as buyer_name, u.email as buyer_email
         FROM purchase_orders po
         LEFT JOIN accounts a ON po.vendor_id = a.id
         LEFT JOIN users u ON po.buyer_id = u.id
         WHERE po.id = ?",
        [$poId]
    );
    
    if (!$po) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        return;
    }
    
    // Check permissions
    if ($userType === 'vendor') {
        $accountId = $_SESSION['active_account_id'] ?? null;
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
    
    // Vendors can also edit vessel and factory date fields
    if ($userType === 'vendor') {
        $editableFields = array_merge($editableFields, ['vessel_name', 'vessel_identifier', 'expected_factory_date']);
    }
    
    // Buyers and admins can edit more fields
    if (in_array($userType, ['buyer', 'admin'])) {
        $editableFields = array_merge($editableFields, ['status', 'buyer_id', 'memo', 'vessel_name', 'vessel_identifier', 'expected_factory_date']);
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
    }
    
    $updateFields[] = "updated_at = NOW()";
    $updateParams[] = $poId;
    
    $sql = "UPDATE purchase_orders SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $db->query($sql, $updateParams);
    
    // Log the change
    Auth::logActivity($userId, 'update_po', "Updated PO #{$po['tran_id']}", [
        'po_id' => $poId,
        'changes' => $changes
    ]);
    
    // Send notification email if vendor made changes
    if ($userType === 'vendor' && $po['buyer_id']) {
        $emailService = new EmailService();
        $buyer = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$po['buyer_id']]);
        
        if ($buyer) {
            $emailService->sendVendorPOUpdate(
                $buyer['email'],
                $po,
                $changes
            );
        }
        
        // Send Teams notification
        try {
            $teamsService = new TeamsService();
            $teamsService->sendVendorPOUpdate($po, $changes, $po['vendor_name']);
        } catch (\Exception $e) {
            error_log("Failed to send Teams notification for PO update: " . $e->getMessage());
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
    
    // Get user info for the comment
    $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    $userName = trim($user['first_name'] . ' ' . $user['last_name']);
    $userType = $user['type'];
    
    $db->query(
        "INSERT INTO po_comments (po_id, user_id, user_name, user_type, comment, created_at) 
         VALUES (?, ?, ?, ?, ?, NOW())",
        [$poId, $userId, $userName, $userType, $comment]
    );
    
    $commentId = $db->lastInsertId();
    
    // Get the comment with user info
    $newComment = $db->fetchOne(
        "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email as user_email
         FROM po_comments c
         LEFT JOIN users u ON c.user_id = u.id
         WHERE c.id = ?",
        [$commentId]
    );
    
    echo json_encode(['success' => true, 'message' => 'Comment added', 'data' => $newComment]);
}

function approveChanges($db, $userType, $userId, $data) {
    if (!in_array($userType, ['buyer', 'admin', 'user'])) {
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
        $nsClient = new NetSuiteClient();
        
        // Update PO in NetSuite
        $updateData = [];
        if ($po['vessel_name']) $updateData['custbodyvessel_name'] = $po['vessel_name'];
        if ($po['vessel_identifier']) $updateData['custbodyvessel_identifier'] = $po['vessel_identifier'];
        if ($po['expected_factory_date']) $updateData['custbodyexpected_factory_date'] = $po['expected_factory_date'];
        if ($po['port_date']) $updateData['custbodyvessel_onboard_date'] = $po['port_date'];
        if ($po['estimated_delivery_date']) $updateData['custbodyus_delivery_date'] = $po['estimated_delivery_date'];
        if ($po['ship_date']) $updateData['custbodyvessel_ship_date'] = $po['ship_date'];
        
        error_log("PO Update Payload for ID {$po['id']}: " . json_encode($updateData));
        $nsClient->updateRecord('purchaseOrder', $po['id'], $updateData);
        
        // Sync all comments from this PO to NetSuite
        $comments = $db->fetchAll(
            "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.type as user_type
             FROM po_comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.po_id = ? AND c.is_internal = 0
             ORDER BY c.created_at ASC",
            [$poId]
        );
        
        foreach ($comments as $comment) {
            $userName = $comment['user_name'] ?: 'Portal User';
            $messageData = [
                'subject' => "Message from {$userName} on Laguna Partner Portal",
                'message' => $comment['comment'],
                'entity' => $po['vendor_id'],
                'messagetype' => 'EMAIL',
                'author' => $po['vendor_id'],
                'incoming' => true,
                'transaction' => $po['id'],
                'primaryRecipient' => $po['buyer_id'],
                'recipient' => $po['buyer_id'],
                'recipientemail' =>'noreply@lagunatools.com'
            ];
            error_log(message: "Message Payload for PO {$po['id']}: " . json_encode($messageData));
            $nsClient->createRecord('message', $messageData);
        }
        
        // Add buyer's approval comment if provided
        if (!empty($data['comment'])) {
            $buyerName = $_SESSION['user_name'] ?? 'Buyer';
            $messageData = [
                'subject' => "Approval Message from {$buyerName} on Laguna Partner Portal",
                'message' => $data['comment'],
                'entity' => $po['vendor_id'],
                'messagetype' => 'EMAIL',
                'author' => $po['vendor_id'],
                'incoming' => true,
                'transaction' => $po['id'],
                'primaryRecipient' => $po['buyer_id'],
                'recipient' => $po['buyer_id']
            ];
            error_log("Approval Message Payload for PO {$po['id']}: " . json_encode($messageData));
            $nsClient->createRecord('message', $messageData);
        }
        
        // Mark as synced
        $db->query(
            "UPDATE purchase_orders 
             SET has_vendor_updates = 0, is_synced_to_netsuite = 1
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
            // Get the approved changes for the email
            $approvedChanges = [];
            if ($po['port_date']) $approvedChanges['port_date'] = ['new' => $po['port_date']];
            if ($po['estimated_delivery_date']) $approvedChanges['estimated_delivery_date'] = ['new' => $po['estimated_delivery_date']];
            if ($po['ship_date']) $approvedChanges['ship_date'] = ['new' => $po['ship_date']];
            
            $emailService->sendBuyerApproval(
                $vendor['email'],
                $po,
                $approvedChanges
            );
        }
        
        Auth::logActivity($userId, 'approve_po_changes', "Approved changes for PO #{$po['tran_id']}", ['po_id' => $poId]);
        
        echo json_encode(['success' => true, 'message' => 'Changes approved and synced to NetSuite']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to sync to NetSuite: ' . $e->getMessage()]);
    }
}

function updateVendorQuantities($db, $userType, $userId, $data) {
    if ($userType !== 'vendor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only vendors can update quantities']);
        return;
    }
    
    $poId = $data['po_id'] ?? null;
    $items = $data['items'] ?? [];
    
    if (!$poId || empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID and items required']);
        return;
    }
    
    // Verify vendor access
    $po = $db->fetchOne("SELECT * FROM purchase_orders WHERE id = ?", [$poId]);
    if (!$po) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        return;
    }
    
    $accountId = $_SESSION['active_account_id'] ?? null;
    if ($po['vendor_id'] != $accountId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    // Update vendor quantities for each item
    $updated = 0;
    foreach ($items as $item) {
        $itemId = $item['item_id'] ?? null;
        $vendorQty = $item['vendor_quantity'] ?? null;
        
        if ($itemId !== null && $vendorQty !== null) {
            $db->query(
                "UPDATE po_items SET vendor_quantity = ? WHERE id = ? AND po_id = ?",
                [$vendorQty, $itemId, $poId]
            );
            $updated++;
        }
    }
    
    // Mark PO as having vendor updates
    $db->query(
        "UPDATE purchase_orders SET has_vendor_updates = 1, is_synced_to_netsuite = 0 WHERE id = ?",
        [$poId]
    );
    
    // Log activity
    Auth::logActivity($userId, 'update_vendor_quantities', "Updated vendor quantities for PO #{$po['tran_id']}", [
        'po_id' => $poId,
        'items_updated' => $updated
    ]);
    
    echo json_encode(['success' => true, 'message' => "Updated $updated item(s)"]);
}

function rejectPO($db, $userType, $userId, $data) {
    if ($userType !== 'vendor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only vendors can reject POs']);
        return;
    }
    
    $poId = $data['po_id'] ?? null;
    $rejectionReason = $data['rejection_reason'] ?? '';
    
    if (!$poId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID required']);
        return;
    }
    
    if (empty(trim($rejectionReason))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
        return;
    }
    
    // Verify vendor access
    $po = $db->fetchOne(
        "SELECT po.*, a.company_name as vendor_name, a.email as vendor_email,
                CONCAT(u.first_name, ' ', u.last_name) as buyer_name, u.email as buyer_email
         FROM purchase_orders po
         LEFT JOIN accounts a ON po.vendor_id = a.id
         LEFT JOIN users u ON po.buyer_id = u.id
         WHERE po.id = ?",
        [$poId]
    );
    
    if (!$po) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        return;
    }
    
    $accountId = $_SESSION['active_account_id'] ?? null;
    if ($po['vendor_id'] != $accountId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    try {
        // Update PO with rejection
        $db->query(
            "UPDATE purchase_orders 
             SET rejection_reason = ?, rejected_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [$rejectionReason, $poId]
        );
        
        // Log activity
        Auth::logActivity($userId, 'reject_po', "Rejected PO #{$po['tran_id']}", [
            'po_id' => $poId,
            'rejection_reason' => $rejectionReason
        ]);
        
        // Send email notification to buyer
        $emailService = new EmailService();
        if ($po['buyer_email']) {
            $emailService->sendPORejection(
                $po['buyer_email'],
                $po,
                $rejectionReason
            );
        }
        
        // Send Teams notification
        try {
            $teamsService = new TeamsService();
            $teamsService->sendPORejection($po, $rejectionReason);
        } catch (\Exception $e) {
            error_log("Failed to send Teams notification for PO rejection: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Purchase order rejected successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to reject PO: ' . $e->getMessage()]);
    }
}

function acceptPO($db, $userType, $userId, $data) {
    if ($userType !== 'vendor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only vendors can accept POs']);
        return;
    }
    
    $poId = $data['po_id'] ?? null;
    
    if (!$poId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID required']);
        return;
    }
    
    // Verify vendor access
    $po = $db->fetchOne(
        "SELECT po.*, a.company_name as vendor_name, a.email as vendor_email,
                CONCAT(u.first_name, ' ', u.last_name) as buyer_name, u.email as buyer_email
         FROM purchase_orders po
         LEFT JOIN accounts a ON po.vendor_id = a.id
         LEFT JOIN users u ON po.buyer_id = u.id
         WHERE po.id = ?",
        [$poId]
    );
    
    if (!$po) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        return;
    }
    
    $accountId = $_SESSION['active_account_id'] ?? null;
    if ($po['vendor_id'] != $accountId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    if ($po['vendor_accepted']) {
        echo json_encode(['success' => false, 'message' => 'This PO has already been accepted']);
        return;
    }
    
    try {
        // Update PO to mark as accepted
        $db->query(
            "UPDATE purchase_orders 
             SET vendor_accepted = 1, vendor_accepted_at = NOW(), updated_at = NOW()
             WHERE id = ?",
            [$poId]
        );
        
        // Log activity
        Auth::logActivity($userId, 'accept_po', "Accepted PO #{$po['tran_id']}", [
            'po_id' => $poId
        ]);
        
        // Send email notification to buyer
        $emailService = new EmailService();
        if ($po['buyer_email']) {
            $emailService->sendPOAcceptance(
                $po['buyer_email'],
                $po
            );
        }
        
        // Send Teams notification
        try {
            $teamsService = new TeamsService();
            $teamsService->sendPOAcceptance($po);
        } catch (\Exception $e) {
            error_log("Failed to send Teams notification for PO acceptance: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Purchase order accepted successfully']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to accept PO: ' . $e->getMessage()]);
    }
}