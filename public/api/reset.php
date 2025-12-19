<?php
/**
 * Portal Reset API
 * Clears all data except admin users for a fresh start
 * WARNING: This action cannot be undone!
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

use LagunaPartners\Auth;
use LagunaPartners\Database;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!Auth::check()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $user = Auth::user();
    if ($user['type'] !== 'user' || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden. Admin access required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['confirm']) || $data['confirm'] !== true) {
        throw new Exception('Reset not confirmed');
    }

    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $startTime = microtime(true);
    $deletedCounts = [];

    try {
        $pdo->beginTransaction();

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $deletedCounts['conversations'] = $pdo->exec('DELETE FROM conversations');
        $deletedCounts['message_attachments'] = $pdo->exec('DELETE FROM message_attachments');
        $deletedCounts['messages'] = $pdo->exec('DELETE FROM messages');
        $deletedCounts['message_participants'] = $pdo->exec('DELETE FROM message_participants');

        $deletedCounts['invoice_attachments'] = $pdo->exec('DELETE FROM invoice_attachments');
        $deletedCounts['invoice_line_items'] = $pdo->exec('DELETE FROM invoice_line_items');
        $deletedCounts['invoice_notes'] = $pdo->exec('DELETE FROM invoice_notes');
        $deletedCounts['invoices'] = $pdo->exec('DELETE FROM invoices');

        $deletedCounts['po_documents'] = $pdo->exec('DELETE FROM po_documents');
        $deletedCounts['po_comments'] = $pdo->exec('DELETE FROM po_comments');
        $deletedCounts['po_items'] = $pdo->exec('DELETE FROM po_items');
        $deletedCounts['purchase_orders'] = $pdo->exec('DELETE FROM purchase_orders');

        $deletedCounts['payment_receipts'] = $pdo->exec('DELETE FROM payment_receipts');
        $deletedCounts['payments'] = $pdo->exec('DELETE FROM payments');
        $deletedCounts['payment_method_preferences'] = $pdo->exec('DELETE FROM payment_method_preferences');

        $deletedCounts['vendor_documents'] = $pdo->exec('DELETE FROM vendor_documents');
        $deletedCounts['vendor_profiles'] = $pdo->exec('DELETE FROM vendor_profiles');

        $deletedCounts['item_notifications'] = $pdo->exec('DELETE FROM item_notifications');
        $deletedCounts['items'] = $pdo->exec('DELETE FROM items');

        $deletedCounts['sync_logs'] = $pdo->exec('DELETE FROM sync_logs');

        $deletedCounts['user_logs'] = $pdo->exec('DELETE FROM user_logs');

        $deletedCounts['user_accounts'] = $pdo->exec('DELETE FROM user_accounts');

        $deletedCounts['otp_codes'] = $pdo->exec('DELETE FROM otp_codes');

        $result = $pdo->exec('DELETE FROM users WHERE NOT (type = "user" AND role = "admin")');
        $deletedCounts['users'] = $result;

        $deletedCounts['accounts'] = $pdo->exec('DELETE FROM accounts');

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $pdo->commit();

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        echo json_encode([
            'success' => true,
            'message' => 'Portal has been reset successfully. All user accounts, purchase orders, invoices, and related data have been cleared.',
            'deleted_records' => array_sum($deletedCounts),
            'details' => $deletedCounts,
            'duration_ms' => $duration
        ]);

        Auth::logActivity($user['id'], 'portal_reset', null, ['deleted_counts' => $deletedCounts]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
