<?php
/**
 * Invoice Attachment Download API
 * 
 * Handles secure invoice attachment downloads with access control
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

session_start();

if (!Auth::check()) {
    http_response_code(401);
    die('Unauthorized');
}

$db = Database::getInstance();
$user = Auth::user();

$attachmentId = intval($_GET['id'] ?? 0);

if (!$attachmentId) {
    http_response_code(400);
    die('Attachment ID is required');
}

try {
    $attachment = $db->fetchOne(
        "SELECT ia.*, i.vendor_id FROM invoice_attachments ia
         JOIN invoices i ON ia.invoice_id = i.id
         WHERE ia.id = ?",
        [$attachmentId]
    );

    if (!$attachment) {
        http_response_code(404);
        die('Attachment not found');
    }

    $hasAccess = false;

    if ($user['type'] === 'user' && in_array($user['role'], ['buyer', 'accounting', 'admin'])) {
        $hasAccess = true;
    } elseif ($user['type'] === 'vendor' && $attachment['vendor_id'] == $user['account_id']) {
        $hasAccess = true;
    }

    if (!$hasAccess) {
        http_response_code(403);
        die('Access denied');
    }

    // Construct file path properly
    $filePath = __DIR__ . '/../..' . $attachment['file_path'];

    if (!file_exists($filePath)) {
        error_log("File not found: " . $filePath . " (stored path: " . $attachment['file_path'] . ")");
        http_response_code(404);
        die('File not found on server: ' . basename($attachment['file_path']));
    }

    if (!is_readable($filePath)) {
        error_log("File not readable: " . $filePath);
        http_response_code(403);
        die('File is not readable');
    }

    try {
        $db->query(
            "INSERT INTO user_logs (user_id, action, details, ip_address, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [
                $user['id'],
                'download_invoice_attachment',
                json_encode([
                    'attachment_id' => $attachmentId,
                    'invoice_id' => $attachment['invoice_id'],
                    'filename' => $attachment['file_name']
                ]),
                $_SERVER['REMOTE_ADDR']
            ]
        );
    } catch (Exception $logError) {
        error_log("User log error (non-critical): " . $logError->getMessage());
    }

    $mimeType = $attachment['file_type'] ?? 'application/octet-stream';
    $fileName = basename($attachment['file_name']);
    $fileSize = filesize($filePath);

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($filePath);
    exit;

} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    http_response_code(500);
    die('Error downloading file: ' . $e->getMessage());
}
