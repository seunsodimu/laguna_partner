<?php
/**
 * Payment Management API
 * Handles payment tracking, history, receipts, and payment method management
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
$db = Database::getInstance();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // Get payment history
        case 'history':
            getPaymentHistory($db, $user);
            break;

        // Get pending payments
        case 'pending':
            getPendingPayments($db, $user);
            break;

        // Get payment details
        case 'get':
            getPaymentDetails($db, $user);
            break;

        // Record new payment
        case 'create':
            createPayment($db, $user);
            break;

        // Update payment status
        case 'update_status':
            updatePaymentStatus($db, $user);
            break;

        // Get payment methods
        case 'payment_methods':
            getPaymentMethods($db, $user);
            break;

        // Save payment method preference
        case 'save_payment_method':
            savePaymentMethod($db, $user);
            break;

        // Delete payment method
        case 'delete_payment_method':
            deletePaymentMethod($db, $user);
            break;

        // Generate payment receipt (PDF)
        case 'generate_receipt':
            generatePaymentReceipt($db, $user);
            break;

        // Get payment statistics
        case 'statistics':
            getPaymentStatistics($db, $user);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ===== FUNCTIONS =====

function getPaymentHistory($db, $user) {
    $vendor_id = $_GET['vendor_id'] ?? null;
    $invoice_id = $_GET['invoice_id'] ?? null;
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    $status = $_GET['status'] ?? null;
    $page = intval($_GET['page'] ?? 1);
    $per_page = intval($_GET['per_page'] ?? 20);
    $offset = ($page - 1) * $per_page;

    // Permission checks
    if ($user['type'] == 'vendor') {
        $vendor_id = $user['account_id'];
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $query = "
        SELECT 
            p.*,
            a.company_name,
            i.invoice_number
        FROM payments p
        JOIN accounts a ON p.vendor_id = a.id
        JOIN invoices i ON p.invoice_id = i.id
        WHERE 1=1
    ";

    $params = [];

    if ($vendor_id) {
        $query .= " AND p.vendor_id = ?";
        $params[] = $vendor_id;
    }

    if ($invoice_id) {
        $query .= " AND p.invoice_id = ?";
        $params[] = $invoice_id;
    }

    if ($date_from) {
        $query .= " AND p.payment_date >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $query .= " AND p.payment_date <= ?";
        $params[] = $date_to;
    }

    if ($status) {
        $query .= " AND p.status = ?";
        $params[] = $status;
    }

    // Get total
    $count_query = "SELECT COUNT(*) as total FROM ($query) as cnt";
    $count_result = $db->fetchOne($count_query, $params);
    $total = $count_result['total'] ?? 0;

    // Get paginated results
    $query .= " ORDER BY p.payment_date DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;

    $payments = $db->fetchAll($query, $params);

    echo json_encode([
        'success' => true,
        'data' => $payments,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ]
    ]);
}

function getPendingPayments($db, $user) {
    // Only buyers and admins can view all pending payments
    // Vendors can view their own pending payments
    if (!in_array($user['type'], ['buyer', 'admin', 'vendor'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $vendor_id = null;
    if ($user['type'] == 'vendor') {
        $vendor_id = $user['account_id'];
    }

    $query = "
        SELECT 
            p.*,
            a.company_name,
            i.invoice_number,
            i.amount_total,
            DATEDIFF(p.expected_arrival_date, NOW()) as days_until_arrival
        FROM payments p
        JOIN accounts a ON p.vendor_id = a.id
        JOIN invoices i ON p.invoice_id = i.id
        WHERE p.status IN ('pending', 'processing')
    ";

    $params = [];

    if ($vendor_id) {
        $query .= " AND p.vendor_id = ?";
        $params[] = $vendor_id;
    }

    $query .= " ORDER BY p.expected_arrival_date ASC";

    $payments = $db->fetchAll($query, $params);

    echo json_encode([
        'success' => true,
        'data' => $payments
    ]);
}

function getPaymentDetails($db, $user) {
    $payment_id = intval($_GET['id'] ?? 0);
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID required']);
        exit;
    }

    $payment = $db->fetchOne("
        SELECT 
            p.*,
            a.company_name,
            i.invoice_number,
            i.description as invoice_description
        FROM payments p
        JOIN accounts a ON p.vendor_id = a.id
        JOIN invoices i ON p.invoice_id = i.id
        WHERE p.id = ?
    ", [$payment_id]);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        exit;
    }

    // Permission check
    if ($user['type'] == 'vendor' && $payment['vendor_id'] != $user['account_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Get receipts
    $payment['receipts'] = $db->fetchAll("SELECT * FROM payment_receipts WHERE payment_id = ?", [$payment_id]);

    echo json_encode([
        'success' => true,
        'data' => $payment
    ]);
}

function createPayment($db, $user) {
    // Only buyers and admins can create payments
    if (!in_array($user['type'], ['buyer', 'admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Only buyers and admins can record payments']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Validation
    if (empty($data['invoice_id']) || empty($data['amount_paid']) || empty($data['payment_method']) || empty($data['payment_date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    // Get invoice
    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$data['invoice_id']]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Generate payment number
    $payment_number = 'PAY-' . date('Ymd') . '-' . rand(1000, 9999);

    $payment_id = $db->insert('payments', [
        'payment_number' => $payment_number,
        'invoice_id' => $data['invoice_id'],
        'vendor_id' => $invoice['vendor_id'],
        'amount_paid' => $data['amount_paid'],
        'payment_date' => $data['payment_date'],
        'payment_method' => $data['payment_method'],
        'status' => $data['status'] ?? 'pending',
        'expected_arrival_date' => $data['expected_arrival_date'] ?? null,
        'reference_number' => $data['reference_number'] ?? null,
        'notes' => $data['notes'] ?? null,
        'created_by_user_id' => $user['id']
    ]);

    // Update invoice paid amount
    $db->update('invoices',
        ['amount_paid' => $invoice['amount_paid'] + $data['amount_paid']],
        'id = ?',
        [$data['invoice_id']]
    );

    // Update invoice status to "paid" if fully paid
    $amount_paid_total = $invoice['amount_paid'] + $data['amount_paid'];
    if ($amount_paid_total >= $invoice['amount_total']) {
        $db->update('invoices', ['status' => 'paid'], 'id = ?', [$data['invoice_id']]);
    } else {
        $db->update('invoices', ['status' => 'processing'], 'id = ?', [$data['invoice_id']]);
    }

    Auth::logActivity($user['id'], 'create_payment', 'payment', [
        'invoice_id' => $data['invoice_id'],
        'amount' => $data['amount_paid'],
        'method' => $data['payment_method'],
        'entity_id' => $payment_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Payment recorded successfully',
        'payment_id' => $payment_id,
        'payment_number' => $payment_number
    ]);
}

function updatePaymentStatus($db, $user) {
    if (!in_array($user['type'], ['buyer', 'admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Only buyers and admins can update payment status']);
        exit;
    }

    $payment_id = intval($_GET['id'] ?? 0);
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Status required']);
        exit;
    }

    $payment = $db->fetchOne("SELECT * FROM payments WHERE id = ?", [$payment_id]);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        exit;
    }

    $db->update('payments', [
        'status' => $data['status'],
        'expected_arrival_date' => $data['expected_arrival_date'] ?? $payment['expected_arrival_date'],
        'notes' => $data['notes'] ?? $payment['notes'],
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$payment_id]);

    Auth::logActivity($user['id'], 'update_payment_status', 'payment', [
        'new_status' => $data['status'],
        'entity_id' => $payment_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Payment status updated successfully'
    ]);
}

function getPaymentMethods($db, $user) {
    $vendor_id = $_GET['vendor_id'] ?? null;

    // Permission check
    if ($user['type'] == 'vendor') {
        $vendor_id = $user['account_id'];
    } elseif (!in_array($user['type'], ['buyer', 'admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if (!$vendor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID required']);
        exit;
    }

    $methods = $db->fetchAll("
        SELECT 
            id, vendor_id, payment_method, is_preferred, is_active,
            account_holder_name, bank_name, payment_method as method_type
        FROM payment_method_preferences
        WHERE vendor_id = ? AND is_active = 1
        ORDER BY is_preferred DESC, payment_method ASC
    ", [$vendor_id]);

    echo json_encode([
        'success' => true,
        'data' => $methods
    ]);
}

function savePaymentMethod($db, $user) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['vendor_id']) || empty($data['payment_method'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    // Permission check
    if ($user['type'] == 'vendor' && $data['vendor_id'] != $user['account_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Check if method already exists
    $existing = $db->fetchOne("
        SELECT id FROM payment_method_preferences
        WHERE vendor_id = ? AND payment_method = ?
    ", [$data['vendor_id'], $data['payment_method']]);

    if ($existing) {
        // Update existing
        $db->update('payment_method_preferences', [
            'is_preferred' => $data['is_preferred'] ? 1 : 0,
            'is_active' => $data['is_active'] !== false ? 1 : 0,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'wire_instructions' => $data['wire_instructions'] ?? null,
            'notes' => $data['notes'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$existing['id']]);

        $method_id = $existing['id'];
    } else {
        // Insert new
        $method_id = $db->insert('payment_method_preferences', [
            'vendor_id' => $data['vendor_id'],
            'payment_method' => $data['payment_method'],
            'is_preferred' => $data['is_preferred'] ? 1 : 0,
            'is_active' => 1,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'wire_instructions' => $data['wire_instructions'] ?? null,
            'notes' => $data['notes'] ?? null
        ]);
    }

    // If set as preferred, unset others
    if ($data['is_preferred']) {
        $db->update('payment_method_preferences',
            ['is_preferred' => 0],
            'vendor_id = ? AND id != ?',
            [$data['vendor_id'], $method_id]
        );
    }

    Auth::logActivity($user['id'], 'save_payment_method', 'vendor_account', [
        'method' => $data['payment_method'],
        'entity_id' => $data['vendor_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Payment method saved successfully',
        'method_id' => $method_id
    ]);
}

function deletePaymentMethod($db, $user) {
    $method_id = intval($_GET['id'] ?? 0);
    if (!$method_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Method ID required']);
        exit;
    }

    $method = $db->fetchOne("SELECT * FROM payment_method_preferences WHERE id = ?", [$method_id]);

    if (!$method) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment method not found']);
        exit;
    }

    // Permission check
    if ($user['type'] == 'vendor' && $method['vendor_id'] != $user['account_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $db->update('payment_method_preferences', ['is_active' => 0], 'id = ?', [$method_id]);

    Auth::logActivity($user['id'], 'delete_payment_method', 'vendor_account', [
        'method' => $method['payment_method'],
        'entity_id' => $method['vendor_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Payment method deleted successfully'
    ]);
}

function generatePaymentReceipt($db, $user) {
    $payment_id = intval($_GET['id'] ?? 0);
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Payment ID required']);
        exit;
    }

    $payment = $db->fetchOne("
        SELECT 
            p.*,
            a.company_name,
            i.invoice_number,
            i.invoice_date,
            i.amount_total
        FROM payments p
        JOIN accounts a ON p.vendor_id = a.id
        JOIN invoices i ON p.invoice_id = i.id
        WHERE p.id = ?
    ", [$payment_id]);

    if (!$payment) {
        http_response_code(404);
        echo json_encode(['error' => 'Payment not found']);
        exit;
    }

    // Permission check
    if ($user['type'] == 'vendor' && $payment['vendor_id'] != $user['account_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Generate PDF receipt
    $file_name = 'payment_receipt_' . $payment['payment_number'] . '.pdf';
    $file_path = '../../uploads/payment_receipts/' . time() . '_' . $file_name;

    // Create directory if needed
    if (!is_dir(dirname($file_path))) {
        mkdir(dirname($file_path), 0755, true);
    }

    // Generate simple PDF (in real implementation, use a PDF library)
    $receipt_content = generateReceiptContent($payment);
    file_put_contents($file_path, $receipt_content);

    // Save receipt record
    $db->insert('payment_receipts', [
        'payment_id' => $payment_id,
        'file_name' => $file_name,
        'file_path' => $file_path,
        'file_size' => filesize($file_path),
        'file_type' => 'application/pdf',
        'receipt_type' => 'remittance_advice'
    ]);

    // Return file for download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    readfile($file_path);
    exit;
}

function generateReceiptContent($payment) {
    $content = "Payment Receipt\n";
    $content .= "===============\n\n";
    $content .= "Payment Number: " . $payment['payment_number'] . "\n";
    $content .= "Payment Date: " . $payment['payment_date'] . "\n";
    $content .= "Invoice Number: " . $payment['invoice_number'] . "\n";
    $content .= "Vendor: " . $payment['company_name'] . "\n";
    $content .= "Amount Paid: " . $payment['amount_paid'] . " " . ($payment['currency'] ?? 'USD') . "\n";
    $content .= "Payment Method: " . ucfirst($payment['payment_method']) . "\n";
    $content .= "Status: " . ucfirst($payment['status']) . "\n";
    if ($payment['expected_arrival_date']) {
        $content .= "Expected Arrival: " . $payment['expected_arrival_date'] . "\n";
    }
    if ($payment['reference_number']) {
        $content .= "Reference Number: " . $payment['reference_number'] . "\n";
    }
    $content .= "\nThank you for your business.";

    return $content;
}

function getPaymentStatistics($db, $user) {
    if ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $vendor_id = null;
    if ($user['type'] == 'vendor') {
        $vendor_id = $user['account_id'];
    }

    $query = "
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
            SUM(amount_paid) as total_paid,
            SUM(CASE WHEN status IN ('pending', 'processing') THEN amount_paid ELSE 0 END) as pending_amount,
            COUNT(DISTINCT payment_method) as payment_methods_used
        FROM payments
    ";
    
    $params = [];

    if ($vendor_id) {
        $query .= " WHERE vendor_id = ?";
        $params[] = $vendor_id;
    }

    $stats = $db->fetchOne($query, $params);

    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}
?>