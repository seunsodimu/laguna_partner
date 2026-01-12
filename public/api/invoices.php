<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

/**
 * Invoice Management API
 * Handles invoice submission, tracking, approval, and status management
 */

require_once '../../config/config.php';
require_once '../../src/Database.php';
require_once '../../src/Auth.php';
require_once '../../src/TeamsService.php';
require_once '../../src/NetSuiteClient.php';
require_once '../../src/EmailService.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;
use LagunaPartners\TeamsService;
use LagunaPartners\NetSuiteClient;
use LagunaPartners\EmailService;

if (!class_exists('Dotenv\Dotenv')) {
    require_once '../../vendor/autoload.php';
}

$dotenvPath = __DIR__ . '/../..';
$dotenv = Dotenv\Dotenv::createImmutable($dotenvPath);
error_log("Loading .env from: " . $dotenvPath . " | Current working directory: " . getcwd() . " | .env exists: " . (file_exists($dotenvPath . '/.env') ? 'YES' : 'NO'));
$dotenv->load();
error_log("After dotenv load - NETSUITE_ENVIRONMENT: " . ($_ENV['NETSUITE_ENVIRONMENT'] ?? 'NOT_SET'));

header('Content-Type: application/json');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST) && empty($_FILES)) {
    $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($content_type, 'application/json') === false) {
        http_response_code(413);
        echo json_encode(['error' => 'Request entity too large. Maximum file size allowed is ' . ini_get('post_max_size')]);
        exit;
    }
}

// Verify user is authenticated
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Auth::user();
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // Get invoices list with filters
        case 'list':
            listInvoices($db, $user);
            break;

        // Get single invoice details
        case 'get':
            getInvoiceDetails($db, $user);
            break;

        // Create new invoice (draft)
        case 'create':
            createInvoice($db, $user);
            break;

        // Update invoice
        case 'update':
            updateInvoice($db, $user);
            break;

        // Submit invoice for review
        case 'submit':
            submitInvoice($db, $user);
            break;

        // Approve invoice (buyer only)
        case 'approve':
            approveInvoice($db, $user);
            break;

        // Request corrections to invoice
        case 'request_correction':
            requestInvoiceCorrection($db, $user);
            break;

        // Add note to invoice
        case 'add_note':
            addInvoiceNote($db, $user);
            break;

        // Get invoice notes
        case 'get_notes':
            getInvoiceNotes($db, $user);
            break;

        // Upload invoice attachment
        case 'upload_attachment':
            uploadInvoiceAttachment($db, $user);
            break;

        // Get aging report
        case 'aging_report':
            getAgingReport($db, $user);
            break;

        // Get invoice statistics
        case 'statistics':
            getInvoiceStatistics($db, $user);
            break;

        // Check if invoice number already exists for vendor
        case 'check_number':
            checkInvoiceNumber($db, $user);
            break;

        // Sync invoice to NetSuite
        case 'sync_to_netsuite':
            syncInvoiceToNetSuiteAPI($db, $user);
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

function listInvoices($db, $user) {
    $vendor_id = $_GET['vendor_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    $search = $_GET['search'] ?? null;
    $page = intval($_GET['page'] ?? 1);
    $per_page = intval($_GET['per_page'] ?? 20);
    $offset = ($page - 1) * $per_page;

    // Permission checks
    if ($user['type'] == 'vendor') {
        // Vendors can only see their own invoices
        $vendor_id = $user['account_id'] ?? null;
        if (!$vendor_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Vendor account not found']);
            exit;
        }
    } elseif ($user['type'] == 'dealer') {
        // Dealers cannot access invoices
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } elseif ($user['type'] == 'user') {
        // Internal users (buyer, accounting, admin) can see all invoices
        if (!in_array($user['role'] ?? '', ['buyer', 'accounting', 'admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
    }
    // Buyers, admins, accounting can see all invoices (or filter by vendor)

    $query = "SELECT i.*, a.company_name as vendor_name,
              (SELECT COUNT(*) FROM invoice_notes WHERE invoice_id = i.id AND is_internal = 0) as note_count,
              (SELECT COUNT(*) FROM invoice_attachments WHERE invoice_id = i.id) as attachment_count
              FROM invoices i
              JOIN accounts a ON i.vendor_id = a.id
              WHERE 1=1";

    $params = [];

    if ($vendor_id) {
        $query .= " AND i.vendor_id = ?";
        $params[] = $vendor_id;
    }

    if ($status) {
        $query .= " AND i.status = ?";
        $params[] = $status;
    }

    if ($date_from) {
        $query .= " AND i.invoice_date >= ?";
        $params[] = $date_from;
    }

    if ($date_to) {
        $query .= " AND i.invoice_date <= ?";
        $params[] = $date_to;
    }

    if ($search) {
        $query .= " AND (i.invoice_number LIKE ? OR a.company_name LIKE ? OR i.description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM invoices i JOIN accounts a ON i.vendor_id = a.id WHERE 1=1";
    $count_params = [];
    
    if ($vendor_id) {
        $count_query .= " AND i.vendor_id = ?";
        $count_params[] = $vendor_id;
    }
    
    if ($status) {
        $count_query .= " AND i.status = ?";
        $count_params[] = $status;
    }
    
    if ($date_from) {
        $count_query .= " AND i.invoice_date >= ?";
        $count_params[] = $date_from;
    }
    
    if ($date_to) {
        $count_query .= " AND i.invoice_date <= ?";
        $count_params[] = $date_to;
    }
    
    if ($search) {
        $count_query .= " AND (i.invoice_number LIKE ? OR a.company_name LIKE ? OR i.description LIKE ?)";
        $search_term = "%$search%";
        $count_params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }
    
    $count_result = $db->fetchOne($count_query, $count_params);
    $total = $count_result['total'] ?? 0;

    // Get paginated results
    $query .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;

    $invoices = $db->fetchAll($query, $params);

    echo json_encode([
        'success' => true,
        'data' => $invoices,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ]
    ]);
}

function getInvoiceDetails($db, $user) {
    $invoice_id = intval($_GET['id'] ?? 0);
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID required']);
        exit;
    }

    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Permission check
    if ($user['type'] == 'vendor' && $invoice['vendor_id'] != $user['account_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Get line items
    $invoice['line_items'] = $db->fetchAll("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY line_number", [$invoice_id]);

    // Get attachments
    $invoice['attachments'] = $db->fetchAll("SELECT * FROM invoice_attachments WHERE invoice_id = ?", [$invoice_id]);

    // Get notes (visible based on permissions)
    $notes_query = "SELECT * FROM invoice_notes WHERE invoice_id = ?";
    if ($user['type'] == 'vendor') {
        $notes_query .= " AND is_internal = 0";  // Vendors see only public notes
    }
    $notes_query .= " ORDER BY created_at DESC";

    $invoice['notes'] = $db->fetchAll($notes_query, [$invoice_id]);

    echo json_encode([
        'success' => true,
        'data' => $invoice
    ]);
}

function createInvoice($db, $user) {
    if ($user['type'] != 'vendor') {
        http_response_code(403);
        echo json_encode(['error' => 'Only vendors can create invoices']);
        exit;
    }

    $payload = null;
    if (isset($_POST['payload'])) {
        $payload = json_decode($_POST['payload'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payload']);
            exit;
        }
    } else {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $payload = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $payload = null;
            }
        }
    }

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    $invoiceNumber = trim($payload['invoice_number'] ?? '');
    $invoiceDate = $payload['invoice_date'] ?? null;
    $invoiceType = $payload['invoice_type'] ?? null;
    $amountTotal = isset($payload['amount_total']) ? floatval($payload['amount_total']) : 0;

    if ($invoiceNumber === '' || !$invoiceDate || !$invoiceType || $amountTotal <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    if (!in_array($invoiceType, ['down_payment', 'regular'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid invoice type']);
        exit;
    }

    $vendorId = $user['account_id'];
    if (!$vendorId) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor account not found']);
        exit;
    }

    $existing = $db->fetchOne("SELECT id FROM invoices WHERE invoice_number = ? AND vendor_id = ?", [$invoiceNumber, $vendorId]);
    if ($existing) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice number already exists for your vendor account']);
        exit;
    }

    $vendor = $db->fetchOne("SELECT company_name FROM accounts WHERE id = ?", [$vendorId]);

    if (!$vendor) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor account not found']);
        exit;
    }

    $poLineItemsInput = isset($payload['po_line_items']) && is_array($payload['po_line_items']) ? $payload['po_line_items'] : [];
    $poLineItems = [];
    $poIds = [];

    foreach ($poLineItemsInput as $item) {
        $poId = intval($item['po_id'] ?? 0);
        $amountBilled = isset($item['amount_billed']) ? floatval($item['amount_billed']) : 0;

        if ($poId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid purchase order selection']);
            exit;
        }

        if (isset($poIds[$poId])) {
            http_response_code(400);
            echo json_encode(['error' => 'Duplicate purchase order selected']);
            exit;
        }

        $poIds[$poId] = true;

        if ($amountBilled < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Purchase order amount billed must be non-negative']);
            exit;
        }

        $poRecord = $db->fetchOne("SELECT id, vendor_id, tran_id, total_amount FROM purchase_orders WHERE id = ?", [$poId]);

        if (!$poRecord || intval($poRecord['vendor_id']) !== intval($vendorId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid purchase order selection']);
            exit;
        }

        $poValue = isset($poRecord['total_amount']) ? floatval($poRecord['total_amount']) : 0;
        if ($poValue < 0) {
            $poValue = 0;
        }

        if ($amountBilled > $poValue) {
            http_response_code(400);
            echo json_encode(['error' => 'Purchase order billed amount cannot exceed PO value']);
            exit;
        }

        $poLineItems[] = [
            'po_id' => $poId,
            'po_number' => $poRecord['tran_id'] ?? $poId,
            'po_value' => $poValue,
            'amount_billed' => $amountBilled
        ];
    }

    $totalPOBilled = array_sum(array_map(function ($item) {
        return $item['amount_billed'];
    }, $poLineItems));

    $totalPOValue = array_sum(array_map(function ($item) {
        return $item['po_value'];
    }, $poLineItems));

    if ($totalPOBilled > $amountTotal) {
        http_response_code(400);
        echo json_encode(['error' => 'Total amount billed from purchase orders cannot exceed the invoice total']);
        exit;
    }
// commented this out. Total PO value CAN exceed the invoice total
    // if ($totalPOValue > $amountTotal) {
    //     http_response_code(400);
    //     echo json_encode(['error' => 'Total purchase order value cannot exceed the invoice total']);
    //     exit;
    // }

    $manualLineItemsInput = isset($payload['line_items']) && is_array($payload['line_items']) ? $payload['line_items'] : [];
    $manualLineItems = [];

    foreach ($manualLineItemsInput as $line) {
        if (!empty($line['po_id'])) {
            continue;
        }

        $description = trim($line['description'] ?? '');
        $quantity = isset($line['quantity']) ? floatval($line['quantity']) : 0;
        $unitPrice = isset($line['unit_price']) ? floatval($line['unit_price']) : 0;
        $amount = isset($line['amount']) ? floatval($line['amount']) : $quantity * $unitPrice;

        if ($description === '' && $quantity == 0 && $unitPrice == 0) {
            continue;
        }

        $manualLineItems[] = [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'reference' => $line['reference'] ?? null
        ];
    }

    $lineItems = [];
    $lineNumber = 1;

    foreach ($poLineItems as $line) {
        $lineItems[] = [
            'line_number' => $lineNumber++,
            'description' => 'PO ' . ($line['po_number'] ?? $line['po_id']),
            'quantity' => 1,
            'unit_price' => $line['amount_billed'],
            'amount' => $line['amount_billed'],
            'reference' => (string)$line['po_id']
        ];
    }

    foreach ($manualLineItems as $line) {
        $lineItems[] = [
            'line_number' => $lineNumber++,
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'amount' => $line['amount'],
            'reference' => $line['reference'] ?? null
        ];
    }

    if (empty($lineItems)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice must include at least one line item']);
        exit;
    }

    $db->beginTransaction();
    $createdPaths = [];

    try {
        $invoiceId = $db->insert('invoices', [
            'invoice_number' => $invoiceNumber,
            'vendor_id' => $vendorId,
            'vendor_name' => $vendor['company_name'],
            'invoice_type' => $invoiceType,
            'po_id' => null,
            'invoice_date' => $invoiceDate,
            'due_date' => $payload['due_date'] ?? null,
            'amount_total' => $amountTotal,
            'currency' => $payload['currency'] ?? 'USD',
            'status' => 'draft',
            'payment_terms' => $payload['payment_terms'] ?? null,
            'estimated_payment_date' => calculateEstimatedPaymentDate($payload['due_date'] ?? null, $payload['payment_terms'] ?? null),
            'description' => $payload['description'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'submitted_by_user_id' => $user['id']
        ]);

        foreach ($lineItems as $line) {
            $db->insert('invoice_line_items', [
                'invoice_id' => $invoiceId,
                'line_number' => $line['line_number'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'amount' => $line['amount'],
                'reference' => $line['reference']
            ]);
        }

        if (!empty($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $uploadInfo = handleInvoiceDocumentUpload($db, $invoiceId, $_FILES['document'], $user['id'], $invoiceNumber, $poLineItems);
            if (!empty($uploadInfo['paths'])) {
                $createdPaths = array_merge($createdPaths, $uploadInfo['paths']);
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        foreach ($createdPaths as $path) {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        return;
    }

    try {
        Auth::logActivity($user['id'], 'create_invoice', 'invoice', [
            'invoice_number' => $invoiceNumber,
            'amount' => $amountTotal,
            'status' => 'draft'
        ]);

        notifyInvoiceCreated($db, $invoiceId);
    } catch (Exception $e) {
        error_log("Post-creation error for invoice $invoiceId: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Invoice created successfully',
        'invoice_id' => $invoiceId
    ]);
}

function handleInvoiceDocumentUpload($db, $invoiceId, $file, $userId, $invoiceNumber, $poLineItems) {
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds limit');
    }

    $allowedTypes = [
        'application/pdf',
        'text/csv',
        'application/xml',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes, true)) {
        throw new Exception('Invalid document type');
    }

    $invoiceDir = __DIR__ . '/../../uploads/invoices/' . $invoiceId;
    if (!is_dir($invoiceDir)) {
        mkdir($invoiceDir, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('invoice_', true);
    if ($extension) {
        $filename .= '.' . $extension;
    }

    $filePath = $invoiceDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload document');
    }

    $paths = [$filePath];
    $fileSize = filesize($filePath);

    $db->query(
        "INSERT INTO invoice_attachments (invoice_id, user_id, file_name, file_path, file_size, file_type, attachment_type)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $invoiceId,
            $userId,
            $file['name'],
            $filePath,
            $fileSize,
            $mimeType,
            'invoice_document'
        ]
    );

    foreach ($poLineItems as $poItem) {
        $poDir = __DIR__ . '/../../uploads/po_documents/' . $poItem['po_id'] . '/';
        if (!is_dir($poDir)) {
            mkdir($poDir, 0755, true);
        }

        $poFilePath = $poDir . '/' . $filename;
        if (!copy($filePath, $poFilePath)) {
            continue;
        }

        $paths[] = $poFilePath;

        $db->query(
            "INSERT INTO po_documents (po_id, user_id, file_name, file_path, file_size, file_type, document_type, comment, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $poItem['po_id'],
                $userId,
                $file['name'],
                $poFilePath,
                filesize($poFilePath),
                $mimeType,
                'Invoice',
                'Invoice ' . $invoiceNumber . ' attachment'
            ]
        );
    }

    return ['paths' => $paths];
}

function updateInvoice($db, $user) {
    if ($user['type'] != 'vendor') {
        http_response_code(403);
        echo json_encode(['error' => 'Only vendors can update invoices']);
        exit;
    }

    $invoice_id = intval($_GET['id'] ?? 0);
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID required']);
        exit;
    }

    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Only allow updates if status is draft or rejected
    if (!in_array($invoice['status'], ['draft', 'rejected'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Can only edit invoices in draft or rejected status']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $db->query("
        UPDATE invoices SET
            invoice_date = COALESCE(?, invoice_date),
            due_date = COALESCE(?, due_date),
            amount_total = COALESCE(?, amount_total),
            currency = COALESCE(?, currency),
            payment_terms = COALESCE(?, payment_terms),
            estimated_payment_date = COALESCE(?, estimated_payment_date),
            description = COALESCE(?, description),
            notes = COALESCE(?, notes),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ", [
        $data['invoice_date'] ?? null,
        $data['due_date'] ?? null,
        $data['amount_total'] ?? null,
        $data['currency'] ?? null,
        $data['payment_terms'] ?? null,
        calculateEstimatedPaymentDate($data['due_date'] ?? $invoice['due_date'], $data['payment_terms'] ?? $invoice['payment_terms']),
        $data['description'] ?? null,
        $data['notes'] ?? null,
        $invoice_id
    ]);

    // Update line items if provided
    if (!empty($data['line_items']) && is_array($data['line_items'])) {
        // Delete existing line items
        $db->query("DELETE FROM invoice_line_items WHERE invoice_id = ?", [$invoice_id]);

        // Insert new line items
        foreach ($data['line_items'] as $line) {
            $db->query("
                INSERT INTO invoice_line_items (invoice_id, line_number, description, quantity, unit_price, amount, reference)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $invoice_id,
                $line['line_number'] ?? 0,
                $line['description'] ?? '',
                $line['quantity'] ?? 0,
                $line['unit_price'] ?? 0,
                $line['amount'] ?? 0,
                $line['reference'] ?? null
            ]);
        }
    }

    Auth::logActivity($user['id'], 'update_invoice', 'invoice', ['status' => 'draft', 'entity_id' => $invoice_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Invoice updated successfully'
    ]);
}

function submitInvoice($db, $user) {
    if ($user['type'] != 'vendor') {
        http_response_code(403);
        echo json_encode(['error' => 'Only vendors can submit invoices']);
        exit;
    }

    $invoice_id = intval($_GET['id'] ?? 0);
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID required']);
        exit;
    }

    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ? AND vendor_id = ?", [$invoice_id, $user['account_id']]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Validate invoice has line items
    $line_result = $db->fetchOne("SELECT COUNT(*) as count FROM invoice_line_items WHERE invoice_id = ?", [$invoice_id]);
    $line_count = $line_result['count'];

    if ($line_count == 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice must have at least one line item']);
        exit;
    }

    // Update status to submitted
    $db->query("
        UPDATE invoices SET
            status = 'submitted',
            submitted_by_user_id = ?,
            submitted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ", [$user['id'], $invoice_id]);

    Auth::logActivity($user['id'], 'submit_invoice', 'invoice', ['status' => 'submitted', 'entity_id' => $invoice_id]);

    // Get vendor name for notification
    $vendor = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$user['account_id']]);
    $vendorName = $vendor['company_name'] ?? 'Unknown Vendor';

    // Send Teams notification
    try {
        $teamsService = new TeamsService();
        $teamsService->sendInvoiceSubmitted($invoice, $vendorName);
    } catch (\Exception $e) {
        error_log("Failed to send Teams notification for invoice submission: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Invoice submitted successfully'
    ]);
}

function approveInvoice($db, $user) {
    // Only buyers and admins can approve
    if ($user['type'] !== 'user' || !in_array($user['role'], ['buyer', 'accounting', 'admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Only buyers and admins can approve invoices']);
        exit;
    }

    $invoice_id = intval($_GET['id'] ?? 0);
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Can only approve submitted, under_review, or processing invoices
    if (!in_array($invoice['status'], ['submitted', 'under_review', 'processing'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice must be submitted, under review, or synced to NetSuite']);
        exit;
    }

    $db->query("
        UPDATE invoices SET
            status = 'approved',
            reviewed_by_user_id = ?,
            reviewed_at = CURRENT_TIMESTAMP,
            approved_by_user_id = ?,
            approved_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ", [$user['id'], $user['id'], $invoice_id]);

    // Add approval note if provided
    if (!empty($data['approval_note'])) {
        $db->query("
            INSERT INTO invoice_notes (invoice_id, user_id, user_name, user_type, note_text, is_internal)
            VALUES (?, ?, ?, ?, ?, 0)
        ", [
            $invoice_id,
            $user['id'],
            $user['first_name'] . ' ' . $user['last_name'],
            $user['type'],
            $data['approval_note']
        ]);
    }

    Auth::logActivity($user['id'], 'approve_invoice', 'invoice', ['status' => 'approved', 'entity_id' => $invoice_id]);

    try {
        $vendor = $db->fetchOne("SELECT email FROM accounts WHERE id = ?", [$invoice['vendor_id']]);
        if ($vendor && $vendor['email']) {
            $emailService = new EmailService();
            $variables = [
                'invoice_number' => $invoice['invoice_number'],
                'amount_total' => '$' . number_format($invoice['amount_total'], 2),
                'currency' => $invoice['currency'] ?? 'USD',
                'due_date' => $invoice['due_date'] ? date('M d, Y', strtotime($invoice['due_date'])) : 'N/A',
                'estimated_payment_date' => $invoice['estimated_payment_date'] ? date('M d, Y', strtotime($invoice['estimated_payment_date'])) : 'To be determined',
                'status' => 'Approved',
                'portal_link' => ($_ENV['APP_BASE_PATH'] ?? '/laguna_partner') . '/vendor/invoice-view.php?id=' . $invoice_id
            ];
            $emailService->sendFromTemplate('invoice_approved', $vendor['email'], $variables);
        }
    } catch (Exception $e) {
        error_log("Failed to send invoice approval email: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Invoice approved successfully'
    ]);
}

function requestInvoiceCorrection($db, $user) {
    // Only buyers and admins can request corrections
    if ($user['type'] !== 'user' || !in_array($user['role'], ['buyer', 'accounting', 'admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Only buyers and admins can request corrections']);
        exit;
    }

    $invoice_id = intval($_GET['id'] ?? 0);
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['correction_reason'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Correction reason required']);
        exit;
    }

    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Update status to rejected
    $db->query("
        UPDATE invoices SET
            status = 'rejected',
            reviewed_by_user_id = ?,
            reviewed_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ", [$user['id'], $invoice_id]);

    // Add correction note
    $db->query("
        INSERT INTO invoice_notes (invoice_id, user_id, user_name, user_type, note_text, is_internal)
        VALUES (?, ?, ?, ?, ?, 0)
    ", [
        $invoice_id,
        $user['id'],
        $user['first_name'] . ' ' . $user['last_name'],
        $user['type'],
        "Correction requested: " . $data['correction_reason']
    ]);

    Auth::logActivity($user['id'], 'request_correction', 'invoice', [
        'reason' => $data['correction_reason'],
        'entity_id' => $invoice_id
    ]);

    try {
        $vendor = $db->fetchOne("SELECT email FROM accounts WHERE id = ?", [$invoice['vendor_id']]);
        if ($vendor && $vendor['email']) {
            $emailService = new EmailService();
            $variables = [
                'invoice_number' => $invoice['invoice_number'],
                'amount_total' => '$' . number_format($invoice['amount_total'], 2),
                'currency' => $invoice['currency'] ?? 'USD',
                'correction_reason' => $data['correction_reason'] ?? '',
                'portal_link' => ($_ENV['APP_BASE_PATH'] ?? '/laguna_partner') . '/vendor/invoice-edit.php?id=' . $invoice_id
            ];
            $emailService->sendFromTemplate('invoice_needs_correction', $vendor['email'], $variables);
        }
    } catch (Exception $e) {
        error_log("Failed to send invoice correction email: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Correction requested successfully'
    ]);
}

function addInvoiceNote($db, $user) {
    $invoice_id = intval($_GET['id'] ?? 0);
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data['note_text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Note text required']);
        exit;
    }

    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Permission check
    if ($user['type'] == 'vendor' && $invoice['vendor_id'] != $user['account_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $is_internal = ($user['type'] != 'vendor') && ($data['is_internal'] ?? false);

    $db->query("
        INSERT INTO invoice_notes (invoice_id, user_id, user_name, user_type, note_text, is_internal)
        VALUES (?, ?, ?, ?, ?, ?)
    ", [
        $invoice_id,
        $user['id'],
        $user['first_name'] . ' ' . $user['last_name'],
        $user['type'],
        $data['note_text'],
        $is_internal
    ]);

    Auth::logActivity($user['id'], 'add_invoice_note', 'invoice', [
        'is_internal' => $is_internal,
        'entity_id' => $invoice_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Note added successfully'
    ]);
}

function getInvoiceNotes($db, $user) {
    $invoice_id = intval($_GET['id'] ?? 0);
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID required']);
        exit;
    }

    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    $notes_query = "SELECT * FROM invoice_notes WHERE invoice_id = ?";
    if ($user['type'] == 'vendor') {
        $notes_query .= " AND is_internal = 0";
    }
    $notes_query .= " ORDER BY created_at DESC";

    $notes = $db->fetchAll($notes_query, [$invoice_id]);

    echo json_encode([
        'success' => true,
        'data' => $notes
    ]);
}

function uploadInvoiceAttachment($db, $user) {
    $invoice_id = intval($_POST['invoice_id'] ?? 0);
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID is required']);
        exit;
    }

    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Please upload a document (PDF or image file)']);
        exit;
    }

    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    // Only vendors can upload to their own drafts/rejected invoices, buyers/admins can upload anytime
    if ($user['type'] == 'vendor') {
        if ($invoice['vendor_id'] != $user['account_id'] || !in_array($invoice['status'], ['draft', 'rejected'])) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only upload documents to your draft or rejected invoices']);
            exit;
        }
    }

    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the maximum allowed size of 100MB',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum allowed size',
            UPLOAD_ERR_PARTIAL => 'File upload was interrupted',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Server error: cannot write file',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by server extension'
        ];
        http_response_code(400);
        echo json_encode(['error' => $error_messages[$file['error']] ?? 'File upload failed']);
        exit;
    }

    // Validate file size (100MB limit)
    $max_size = 100 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        http_response_code(413);
        echo json_encode([
            'error' => 'File is too large',
            'message' => 'The maximum file size allowed is 100MB. Your file is ' . round($file['size'] / 1024 / 1024, 2) . 'MB.'
        ]);
        exit;
    }

    // Validate file type - Allow PDF and common image formats
    $allowed_mimes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_mimes)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid file type',
            'message' => 'Please upload a PDF document or image file (JPG, PNG, GIF, or WebP format)'
        ]);
        exit;
    }

    // Validate file extension matches MIME type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_ext, $allowed_extensions)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid file extension',
            'message' => 'File extension must be .pdf, .jpg, .png, .gif, or .webp'
        ]);
        exit;
    }

    // Create upload directory
    $upload_dir = __DIR__ . '/../../uploads/invoices';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Save file
    $file_name = $file['name'];
    $unique_name = time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $full_file_path = $upload_dir . '/' . $unique_name;

    if (!move_uploaded_file($file['tmp_name'], $full_file_path)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload file']);
        exit;
    }

    // Store relative path for database
    $db_file_path = '/uploads/invoices/' . $unique_name;

    // Save attachment record
    $db->query("
        INSERT INTO invoice_attachments (
            invoice_id, user_id, file_name, file_path, file_size, file_type, attachment_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ", [
        $invoice_id,
        $user['id'],
        $file_name,
        $db_file_path,
        $file['size'],
        $mime_type,
        $_POST['attachment_type'] ?? 'supporting_document'
    ]);

    Auth::logActivity($user['id'], 'upload_invoice_attachment', 'invoice', [
        'file_name' => $file_name,
        'entity_id' => $invoice_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully'
    ]);
}

function getAgingReport($db, $user) {
    // Only buyers and admins can view aging reports
    if ($user['type'] !== 'user' || !in_array($user['role'], ['buyer', 'accounting', 'admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $vendor_id = $_GET['vendor_id'] ?? null;

    $query = "
        SELECT 
            i.id,
            i.invoice_number,
            i.vendor_name,
            i.amount_total,
            i.due_date,
            i.status,
            DATEDIFF(NOW(), i.due_date) as days_overdue,
            CASE 
                WHEN DATEDIFF(NOW(), i.due_date) > 90 THEN '90+ days'
                WHEN DATEDIFF(NOW(), i.due_date) > 60 THEN '60-90 days'
                WHEN DATEDIFF(NOW(), i.due_date) > 30 THEN '30-60 days'
                WHEN DATEDIFF(NOW(), i.due_date) > 0 THEN '0-30 days'
                ELSE 'Not yet due'
            END as aging_bucket
        FROM invoices i
        WHERE i.status IN ('submitted', 'under_review', 'approved') 
            AND i.due_date < CURDATE()
    ";

    $params = [];

    if ($vendor_id) {
        $query .= " AND i.vendor_id = ?";
        $params[] = $vendor_id;
    }

    $query .= " ORDER BY i.due_date ASC";

    $invoices = $db->fetchAll($query, $params);

    // Calculate summary
    $summary = [
        '0-30' => 0,
        '30-60' => 0,
        '60-90' => 0,
        '90+' => 0,
        'not_due' => 0
    ];

    $total_outstanding = 0;

    foreach ($invoices as $invoice) {
        $days = $invoice['days_overdue'];
        if ($days > 90) {
            $summary['90+'] += $invoice['amount_total'];
        } elseif ($days > 60) {
            $summary['60-90'] += $invoice['amount_total'];
        } elseif ($days > 30) {
            $summary['30-60'] += $invoice['amount_total'];
        } else {
            $summary['0-30'] += $invoice['amount_total'];
        }
        $total_outstanding += $invoice['amount_total'];
    }

    echo json_encode([
        'success' => true,
        'data' => $invoices,
        'summary' => $summary,
        'total_outstanding' => $total_outstanding
    ]);
}

function getInvoiceStatistics($db, $user) {
    if ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $vendor_id = null;
    if ($user['type'] == 'vendor') {
        $vendor_id = $user['account_id'];
    }

    // Build query based on user type
    $where = "";
    $params = [];

    if ($vendor_id) {
        $where = " WHERE vendor_id = ?";
        $params[] = $vendor_id;
    }

    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_invoices,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as review_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(amount_total) as total_amount,
            SUM(CASE WHEN status = 'paid' THEN amount_total ELSE 0 END) as paid_amount,
            SUM(CASE WHEN status != 'paid' THEN amount_total ELSE 0 END) as outstanding_amount
        FROM invoices $where
    ", $params);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

function syncInvoiceToNetSuiteAPI($db, $user) {
    // Only buyers and admins can sync invoices
    if ($user['type'] !== 'user' || !in_array($user['role'], ['buyer', 'accounting', 'admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Only buyers and admins can sync invoices to NetSuite']);
        exit;
    }

    $invoice_id = intval($_GET['id'] ?? 0);
    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice ID required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $department_id = intval($data['department_id'] ?? 0);
    $location_id = intval($data['location_id'] ?? 0);
    $posting_period_id = intval($data['posting_period_id'] ?? 0);

    if (!$department_id || !$location_id || !$posting_period_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Department, Location, and Posting Period are required']);
        exit;
    }

    $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);

    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['error' => 'Invoice not found']);
        exit;
    }

    if (!in_array($invoice['status'], ['submitted', 'under_review', 'approved'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice must be submitted, under review, or approved to sync to NetSuite']);
        exit;
    }

    if ($invoice['netsuite_bill_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice has already been synced to NetSuite']);
        exit;
    }

    try {
        $result = syncInvoiceToNetSuite($db, $invoice_id, $department_id, $location_id, $posting_period_id);
        
        if ($result) {
            // Update invoice with department, location, and posting period
            $db->query(
                "UPDATE invoices SET department_id = ?, location_id = ?, postingperiod = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$department_id, $location_id, $posting_period_id, $invoice_id]
            );

            // Update line items with department and location
            $db->query(
                "UPDATE invoice_line_items SET department_id = ?, location_id = ? WHERE invoice_id = ?",
                [$department_id, $location_id, $invoice_id]
            );

            Auth::logActivity($user['id'], 'sync_invoice_to_netsuite', 'invoice', ['invoice_id' => $invoice_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Invoice synced to NetSuite successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to sync invoice to NetSuite']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function syncInvoiceToNetSuite($db, $invoiceId, $departmentId, $locationId, $postingPeriodId) {
    try {
        $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
        if (!$invoice) {
            throw new Exception('Invoice not found');
        }

        $vendor = $db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$invoice['vendor_id']]);
        if (!$vendor) {
            throw new Exception('Vendor not found');
        }

        $line_items = $db->fetchAll("SELECT * FROM invoice_line_items WHERE invoice_id = ? ORDER BY line_number", [$invoiceId]);
        if (empty($line_items)) {
            throw new Exception('Invoice has no line items');
        }

        $items = [];
        $lineNumber = 1;
        foreach ($line_items as $line) {
            $itemId = null;
            
            if ($line['reference'] && is_numeric($line['reference'])) {
                $poId = intval($line['reference']);
                $poItem = $db->fetchOne(
                    "SELECT item_id FROM po_items WHERE po_id = ? ORDER BY line_number LIMIT 1",
                    [$poId]
                );
                if ($poItem && $poItem['item_id']) {
                    $itemId = $poItem['item_id'];
                }
            }
            
            $item = [
                'amount' => floatval($line['amount']),
                'department' => [
                    'id' => (string)$departmentId
                ],
                'inventoryDetail' => [
                    'inventoryAssignment' => [
                        'items' => [],
                        'totalResults' => 0
                    ]
                ],
                'item' => [
                    'id' => (string)($itemId ?? '')
                ],
                'line' => $lineNumber,
                'location' => [
                    'id' => (string)$locationId
                ],
                'orderLine' => $lineNumber,
                'quantity' => floatval($line['quantity']),
                'rate' => floatval($line['unit_price'])
            ];
            
            if ($line['reference'] && is_numeric($line['reference'])) {
                $item['orderDoc'] = ['id' => (string)$line['reference']];
            }
            
            $items[] = $item;
            $lineNumber++;
        }

        $payload = [
            'approvalStatus' => [
                'id' => '2'
            ],
            'createdDate' => $invoice['invoice_date'] . 'T00:00:00Z',
            'department' => [
                'id' => (string)$departmentId
            ],
            'dueDate' => $invoice['due_date'] ?? date('Y-m-d'),
            'entity' => [
                'id' => (string)($vendor['netsuite_id'] ?? '481425')
            ],
            'item' => [
                'items' => array_filter($items),
                'totalResults' => count($items)
            ],
            'lastModifiedDate' => date('c'),
            'location' => [
                'id' => (string)$locationId
            ],
            'memo' => $invoice['invoice_number'],
            'postingPeriod' => [
                'id' => (string)$postingPeriodId
            ],
            'prevDate' => $invoice['invoice_date'] ?? date('Y-m-d'),
            'subsidiary' => [
                'id' => '1'
            ],
            'terms' => [
                'id' => '3'
            ],
            'total' => floatval($invoice['amount_total']),
            'tranDate' => $invoice['invoice_date'] ?? date('Y-m-d'),
            'tranId' => $invoice['invoice_number'],
            'userTotal' => floatval($invoice['amount_total'])
        ];

        $client = new NetSuiteClient();
        $netsuite_bill_id = createVendorBillInNetSuite($payload);

        if ($netsuite_bill_id) {
            $db->query(
                "UPDATE invoices SET netsuite_bill_id = ?, status = 'processing', updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$netsuite_bill_id, $invoiceId]
            );
            return true;
        }

        return false;
    } catch (Exception $e) {
        $env = strtoupper($_ENV['NETSUITE_ENVIRONMENT'] ?? 'SANDBOX');
        $account_key = 'NETSUITE_' . $env . '_ACCOUNT_ID';
        $key_key = 'NETSUITE_' . $env . '_CONSUMER_KEY';
        $base_url_key = 'NETSUITE_' . $env . '_BASE_URL';
        $accountId = $_ENV[$account_key] ?? 'NOT_SET';
        $baseUrl = $_ENV[$base_url_key] ?? 'NOT_SET';
        
        $envVarsStatus = "Expected Keys: [$account_key, $key_key, $base_url_key] | ";
        $envVarsStatus .= "Account Key ($account_key): " . (isset($_ENV[$account_key]) ? 'SET' : 'NOT_SET') . " | ";
        $envVarsStatus .= "Key Key ($key_key): " . (isset($_ENV[$key_key]) ? 'SET' : 'NOT_SET') . " | ";
        
        error_log("Failed to sync invoice $invoiceId to NetSuite (Account: $accountId, URL: $baseUrl, Env: $env, Status: $envVarsStatus): " . $e->getMessage());
        throw $e;
    }
}

function createVendorBillInNetSuite($payload) {
    $env = strtoupper($_ENV['NETSUITE_ENVIRONMENT'] ?? 'SANDBOX');
    
    $account_key = 'NETSUITE_' . $env . '_ACCOUNT_ID';
    $key_key = 'NETSUITE_' . $env . '_CONSUMER_KEY';
    $secret_key = 'NETSUITE_' . $env . '_CONSUMER_SECRET';
    $token_key = 'NETSUITE_' . $env . '_TOKEN_ID';
    $token_secret_key = 'NETSUITE_' . $env . '_TOKEN_SECRET';
    
    // Log all NETSUITE_* environment variables for debugging
    $netsuitEnvVars = array_filter($_ENV, function($key) { return strpos($key, 'NETSUITE_') === 0; }, ARRAY_FILTER_USE_KEY);
    error_log("Invoice Sync - Environment variables loaded: " . json_encode(array_keys($netsuitEnvVars)));
    error_log("Invoice Sync - Looking for credentials | Env: $env | Account Key: $account_key | Key Key: $key_key | Account Set: " . (isset($_ENV[$account_key]) ? 'YES' : 'NO') . " | Key Set: " . (isset($_ENV[$key_key]) ? 'YES' : 'NO'));
    
    if (!isset($_ENV[$account_key]) || !isset($_ENV[$key_key])) {
        error_log("Invoice Sync Failed - Credentials not found for environment: $env | Expected keys: [$account_key, $key_key, $secret_key, $token_key, $token_secret_key] | Available keys: " . json_encode(array_keys($netsuitEnvVars)));
        throw new Exception('NetSuite credentials not configured for environment: ' . strtolower($env));
    }

    $account_id = $_ENV[$account_key];
    $consumer_key = $_ENV[$key_key];
    $consumer_secret = $_ENV[$secret_key];
    $token_id = $_ENV[$token_key];
    $token_secret = $_ENV[$token_secret_key];

    $base_url_key = 'NETSUITE_' . $env . '_BASE_URL';
    $base_url = $_ENV[$base_url_key] ?? 'https://' . $account_id . '.suitetalk.api.netsuite.com/services/rest';
    $url = $base_url . '/record/v1/vendorbill/';

    $oauth = [
        'oauth_consumer_key' => $consumer_key,
        'oauth_token' => $token_id,
        'oauth_signature_method' => 'HMAC-SHA256',
        'oauth_timestamp' => time(),
        'oauth_nonce' => bin2hex(random_bytes(16)),
        'oauth_version' => '1.0'
    ];

    $baseString = 'POST&' . rawurlencode($url) . '&';
    
    $params = [];
    foreach ($oauth as $key => $value) {
        $params[rawurlencode($key)] = rawurlencode($value);
    }
    ksort($params);
    
    $paramString = [];
    foreach ($params as $key => $value) {
        $paramString[] = "$key=$value";
    }
    
    $baseString .= rawurlencode(implode('&', $paramString));

    $signingKey = rawurlencode($consumer_secret) . '&' . rawurlencode($token_secret);
    $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
    $oauth['oauth_signature'] = $signature;

    $oauthHeader = 'OAuth realm="' . $account_id . '",';
    $oauthParts = [];
    foreach ($oauth as $key => $value) {
        $oauthParts[] = $key . '="' . rawurlencode($value) . '"';
    }
    $oauthHeader .= implode(',', $oauthParts);

    $payloadJson = json_encode($payload);
    
    error_log("=== NetSuite Sync Request ===");
    error_log("URL: " . $url);
    error_log("Payload: " . $payloadJson);
    error_log("===========================");
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Prefer: transient',
            'Authorization: ' . $oauthHeader
        ],
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("=== NetSuite Response ===");
    error_log("HTTP Code: " . $httpCode);
    error_log("Response: " . $response);
    error_log("========================");

    if ($error) {
        error_log("cURL Error: $error");
        throw new Exception("cURL Error: $error");
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $env = strtoupper($_ENV['NETSUITE_ENVIRONMENT'] ?? 'SANDBOX');
        $account_key = 'NETSUITE_' . $env . '_ACCOUNT_ID';
        $base_url_key = 'NETSUITE_' . $env . '_BASE_URL';
        $accountId = $_ENV[$account_key] ?? 'Unknown';
        $baseUrl = $_ENV[$base_url_key] ?? 'Unknown';
        error_log("NetSuite API Error (HTTP $httpCode) | Account: $accountId, URL: $baseUrl, Env: $env: $response");
        
        $errorMessage = "NetSuite API Error: HTTP $httpCode";
        
        if ($httpCode === 400) {
            $responseBody = substr($response, $header_size);
            $errorData = json_decode($responseBody, true);
            
            if (isset($errorData['o:errorDetails']) && is_array($errorData['o:errorDetails']) && count($errorData['o:errorDetails']) > 0) {
                $firstError = $errorData['o:errorDetails'][0];
                $errorCode = $firstError['o:errorCode'] ?? 'UNKNOWN_ERROR';
                $detail = $firstError['detail'] ?? 'Unknown error';
                $errorMessage = "NetSuite: $errorCode: $detail";
            }
        }
        
        throw new Exception($errorMessage);
    }

    $headers = substr($response, 0, $header_size);
    
    if (preg_match('/Location:\s*https:\/\/[^\/]+\/services\/rest\/record\/v1\/vendorbill\/(\d+)/i', $headers, $matches)) {
        return $matches[1];
    }

    return null;
}

function notifyInvoiceCreated($db, $invoiceId) {
    return true;
}

function checkInvoiceNumber($db, $user) {
    if ($user['type'] != 'vendor') {
        http_response_code(403);
        echo json_encode(['error' => 'Only vendors can check invoice numbers']);
        exit;
    }

    $invoice_number = $_GET['invoice_number'] ?? '';
    if (!$invoice_number) {
        http_response_code(400);
        echo json_encode(['error' => 'Invoice number required']);
        exit;
    }

    $vendor_id = $user['account_id'];
    if (!$vendor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor account not found']);
        exit;
    }

    $existing = $db->fetchOne(
        "SELECT id FROM invoices WHERE invoice_number = ? AND vendor_id = ?",
        [$invoice_number, $vendor_id]
    );

    echo json_encode([
        'success' => true,
        'exists' => $existing ? true : false
    ]);
}

function calculateEstimatedPaymentDate($due_date, $payment_terms) {
    if (!$due_date) {
        return null;
    }

    if (!$payment_terms) {
        return $due_date;
    }

    if (preg_match('/Net\s+(\d+)/i', $payment_terms, $matches)) {
        $days = intval($matches[1]);
        $date = new DateTime($due_date);
        $date->modify("+{$days} days");
        return $date->format('Y-m-d');
    }

    return $due_date;
}
?>