<?php
/**
 * Document Download API
 * 
 * Handles secure document downloads with access control
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

// Start session
session_start();

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    die('Unauthorized');
}

$db = Database::getInstance();
$user = Auth::user();

// Get document ID from query parameter
$documentId = $_GET['id'] ?? null;

if (!$documentId) {
    http_response_code(400);
    die('Document ID is required');
}

try {
    // Get document details
    $document = $db->fetchOne(
        "SELECT d.*, po.vendor_id, po.buyer_id 
         FROM po_documents d
         JOIN purchase_orders po ON d.po_id = po.id
         WHERE d.id = ?",
        [$documentId]
    );

    if (!$document) {
        http_response_code(404);
        die('Document not found');
    }

    // Check access permissions
    $hasAccess = false;

    switch ($user['type']) {
        case 'admin':
        case 'buyer':
            // Admins and buyers can access all documents
            $hasAccess = true;
            break;

        case 'vendor':
            // Vendors can only access documents for their POs
            $vendorAccounts = $db->fetchAll(
                "SELECT account_id FROM user_accounts WHERE user_id = ?",
                [$user['id']]
            );
            $accountIds = array_column($vendorAccounts, 'account_id');
            
            if (in_array($document['vendor_id'], $accountIds)) {
                $hasAccess = true;
            }
            break;

        case 'dealer':
            // Dealers don't have access to PO documents
            $hasAccess = false;
            break;
    }

    if (!$hasAccess) {
        http_response_code(403);
        die('Access denied');
    }

    // Build file path
    $filePath = __DIR__ . '/../../uploads/' . $document['file_path'];

    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File not found on server');
    }

    // Log the download
    $db->query(
        "INSERT INTO user_logs (user_id, action, details, ip_address, created_at) 
         VALUES (?, ?, ?, ?, NOW())",
        [
            $user['id'],
            'download_document',
            json_encode([
                'document_id' => $documentId,
                'po_id' => $document['po_id'],
                'filename' => $document['filename']
            ]),
            $_SERVER['REMOTE_ADDR']
        ]
    );

    // Set headers for download
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Disposition: attachment; filename="' . basename($document['filename']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file
    readfile($filePath);
    exit;

} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    die('Error downloading file');
}