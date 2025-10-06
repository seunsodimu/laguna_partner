<?php
/**
 * File Upload API Endpoint
 * Handles document uploads for purchase orders
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

header('Content-Type: application/json');

// Start session and check authentication
session_start();
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'] ?? 0;
$userType = $_SESSION['user_type'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    $poId = $_POST['po_id'] ?? null;
    $comment = $_POST['comment'] ?? '';
    
    if (!$poId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'PO ID required']);
        exit;
    }
    
    // Verify PO exists and user has access
    $po = $db->fetchOne("SELECT * FROM purchase_orders WHERE id = ?", [$poId]);
    
    if (!$po) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit;
    }
    
    // Check vendor access
    if ($userType === 'vendor') {
        $accountId = $_SESSION['current_account_id'] ?? null;
        if ($po['vendor_id'] != $accountId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['file'];
    
    // Validate file size (10MB max)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
        exit;
    }
    
    // Validate file type
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv'
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../uploads/po_documents/' . $poId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }
    
    // Save to database
    $db->query(
        "INSERT INTO po_documents (po_id, filename, original_filename, file_path, file_size, mime_type, uploaded_by, comment, uploaded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            $poId,
            $filename,
            $file['name'],
            $filePath,
            $file['size'],
            $mimeType,
            $userId,
            $comment
        ]
    );
    
    $docId = $db->lastInsertId();
    
    // Get the document with user info
    $document = $db->fetchOne(
        "SELECT d.*, u.name as uploaded_by_name
         FROM po_documents d
         LEFT JOIN users u ON d.uploaded_by = u.id
         WHERE d.id = ?",
        [$docId]
    );
    
    // Log activity
    Auth::logActivity($userId, 'upload_document', "Uploaded document to PO #{$po['tranid']}", [
        'po_id' => $poId,
        'filename' => $file['name']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'data' => $document
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}