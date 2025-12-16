<?php
/**
 * Vendor Profile Management API
 * Handles vendor self-service profile editing, banking info, and document management
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
        // Get vendor profile
        case 'get':
            getVendorProfile($db, $user);
            break;

        // Update vendor profile
        case 'update':
            updateVendorProfile($db, $user);
            break;

        // Get vendor documents
        case 'documents':
            getVendorDocuments($db, $user);
            break;

        // Upload vendor document
        case 'upload_document':
            uploadVendorDocument($db, $user);
            break;

        // Delete vendor document
        case 'delete_document':
            deleteVendorDocument($db, $user);
            break;

        // Save payment method preference
        case 'save_payment_method':
            savePaymentMethodPreference($db, $user);
            break;

        // Get payment method preferences
        case 'payment_preferences':
            getPaymentPreferences($db, $user);
            break;

        // Get vendor term info
        case 'get_term_info':
            getVendorTermInfo($db, $user);
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

function getVendorProfile($db, $user) {
    $vendor_id = $_GET['vendor_id'] ?? null;

    // Permission checks
    if ($user['type'] == 'vendor') {
        $vendor_id = $user['account_id'];
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } elseif (!$vendor_id && !in_array($user['type'], ['buyer', 'admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if (!$vendor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID required']);
        exit;
    }

    // Get account info
    $account = $db->fetchOne("SELECT * FROM accounts WHERE id = ? AND type = 'vendor'", [$vendor_id]);

    if (!$account) {
        http_response_code(404);
        echo json_encode(['error' => 'Vendor account not found']);
        exit;
    }

    // Get or create vendor profile
    $profile = $db->fetchOne("SELECT * FROM vendor_profiles WHERE vendor_id = ?", [$vendor_id]);

    if (!$profile) {
        // Create default profile
        $db->query("INSERT INTO vendor_profiles (vendor_id) VALUES (?)", [$vendor_id]);
        $profile = [
            'vendor_id' => $vendor_id,
            'tax_id' => null,
            'primary_contact_name' => null,
            'primary_contact_email' => $account['email'],
            'primary_contact_phone' => $account['phone'],
            'secondary_contact_name' => null,
            'secondary_contact_email' => null,
            'secondary_contact_phone' => null,
            'billing_address_1' => $account['address'] ?? '',
            'billing_address_2' => null,
            'billing_city' => null,
            'billing_state' => null,
            'billing_zip' => null,
            'billing_country' => 'US',
            'shipping_address_1' => null,
            'shipping_address_2' => null,
            'shipping_city' => null,
            'shipping_state' => null,
            'shipping_zip' => null,
            'shipping_country' => 'US',
            'preferred_communication' => 'email'
        ];
    }

    // Combine account and profile data
    $data = array_merge($account, $profile);

    // Get document summary
    $data['documents_summary'] = $db->fetchAll("
        SELECT document_type, COUNT(*) as count, MAX(expiration_date) as latest_expiration
        FROM vendor_documents
        WHERE vendor_id = ?
        GROUP BY document_type
    ", [$vendor_id]);

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
}

function updateVendorProfile($db, $user) {
    $vendor_id = $_GET['vendor_id'] ?? null;

    // Permission checks
    if ($user['type'] == 'vendor') {
        $vendor_id = $user['account_id'];
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if (!$vendor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Update account info (limited fields)
    $db->query("
        UPDATE accounts SET
            email = COALESCE(?, email),
            phone = COALESCE(?, phone),
            company_name = COALESCE(?, company_name),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ", [
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['company_name'] ?? null,
        $vendor_id
    ]);

    // Get or create vendor profile
    $profile_exists = $db->fetchOne("SELECT id FROM vendor_profiles WHERE vendor_id = ?", [$vendor_id]);

    if (!$profile_exists) {
        $db->query("INSERT INTO vendor_profiles (vendor_id) VALUES ?", [$vendor_id]);
    }

    // Update vendor profile
    $db->query("
        UPDATE vendor_profiles SET
            tax_id = COALESCE(?, tax_id),
            primary_contact_name = COALESCE(?, primary_contact_name),
            primary_contact_email = COALESCE(?, primary_contact_email),
            primary_contact_phone = COALESCE(?, primary_contact_phone),
            secondary_contact_name = COALESCE(?, secondary_contact_name),
            secondary_contact_email = COALESCE(?, secondary_contact_email),
            secondary_contact_phone = COALESCE(?, secondary_contact_phone),
            billing_address_1 = COALESCE(?, billing_address_1),
            billing_address_2 = COALESCE(?, billing_address_2),
            billing_city = COALESCE(?, billing_city),
            billing_state = COALESCE(?, billing_state),
            billing_zip = COALESCE(?, billing_zip),
            billing_country = COALESCE(?, billing_country),
            shipping_address_1 = COALESCE(?, shipping_address_1),
            shipping_address_2 = COALESCE(?, shipping_address_2),
            shipping_city = COALESCE(?, shipping_city),
            shipping_state = COALESCE(?, shipping_state),
            shipping_zip = COALESCE(?, shipping_zip),
            shipping_country = COALESCE(?, shipping_country),
            preferred_communication = COALESCE(?, preferred_communication),
            notes = COALESCE(?, notes),
            updated_at = CURRENT_TIMESTAMP
        WHERE vendor_id = ?
    ", [
        $data['tax_id'] ?? null,
        $data['primary_contact_name'] ?? null,
        $data['primary_contact_email'] ?? null,
        $data['primary_contact_phone'] ?? null,
        $data['secondary_contact_name'] ?? null,
        $data['secondary_contact_email'] ?? null,
        $data['secondary_contact_phone'] ?? null,
        $data['billing_address_1'] ?? null,
        $data['billing_address_2'] ?? null,
        $data['billing_city'] ?? null,
        $data['billing_state'] ?? null,
        $data['billing_zip'] ?? null,
        $data['billing_country'] ?? null,
        $data['shipping_address_1'] ?? null,
        $data['shipping_address_2'] ?? null,
        $data['shipping_city'] ?? null,
        $data['shipping_state'] ?? null,
        $data['shipping_zip'] ?? null,
        $data['shipping_country'] ?? null,
        $data['preferred_communication'] ?? null,
        $data['notes'] ?? null,
        $vendor_id
    ]);

    $auth->logActivity($user['id'], 'update_vendor_profile', 'vendor_account', $vendor_id, [
        'fields_updated' => array_keys($data)
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
}

function getVendorDocuments($db, $user) {
    $vendor_id = $_GET['vendor_id'] ?? null;
    $document_type = $_GET['document_type'] ?? null;

    // Permission checks
    if ($user['type'] == 'vendor') {
        $vendor_id = $user['account_id'];
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if (!$vendor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID required']);
        exit;
    }

    $query = "
        SELECT 
            vd.*,
            u.first_name as uploaded_by_name,
            u2.first_name as verified_by_name,
            DATEDIFF(vd.expiration_date, NOW()) as days_until_expiration
        FROM vendor_documents vd
        LEFT JOIN users u ON vd.uploaded_by_user_id = u.id
        LEFT JOIN users u2 ON vd.verified_by_user_id = u2.id
        WHERE vd.vendor_id = ?
    ";

    $params = [$vendor_id];

    if ($document_type) {
        $query .= " AND vd.document_type = ?";
        $params[] = $document_type;
    }

    $query .= " ORDER BY vd.created_at DESC";

    $documents = $db->fetchAll($query, $params);

    echo json_encode([
        'success' => true,
        'data' => $documents
    ]);
}

function uploadVendorDocument($db, $user) {
    $vendor_id = $_POST['vendor_id'] ?? null;
    $document_type = $_POST['document_type'] ?? null;

    if (!$vendor_id || !$document_type) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID and document type required']);
        exit;
    }

    // Permission checks
    if ($user['type'] == 'vendor' && $vendor_id != $user['account_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Can only upload to your own profile']);
        exit;
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded']);
        exit;
    }

    // Validate file
    $file = $_FILES['file'];
    $max_size = 10 * 1024 * 1024; // 10MB
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword'];

    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['error' => 'File too large (max 10MB)']);
        exit;
    }

    if (!in_array($file['type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Only PDF, JPEG, PNG, and DOC files allowed']);
        exit;
    }

    // Create upload directory
    $upload_dir = '../../uploads/vendor_documents';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Save file
    $file_name = $file['name'];
    $file_path = $upload_dir . '/' . $vendor_id . '_' . time() . '_' . basename($file_name);

    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload file']);
        exit;
    }

    // Save document record
    $document_id = $db->insert('vendor_documents', [
        'vendor_id' => $vendor_id,
        'document_type' => $document_type,
        'file_name' => $file_name,
        'file_path' => $file_path,
        'file_size' => $file['size'],
        'file_type' => $file['type'],
        'expiration_date' => $_POST['expiration_date'] ?? null,
        'uploaded_by_user_id' => $user['id'],
        'notes' => $_POST['notes'] ?? null
    ]);

    $auth->logActivity($user['id'], 'upload_vendor_document', 'vendor_document', $document_id, [
        'document_type' => $document_type,
        'file_name' => $file_name
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'document_id' => $document_id
    ]);
}

function deleteVendorDocument($db, $user) {
    $document_id = intval($_GET['id'] ?? 0);
    if (!$document_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Document ID required']);
        exit;
    }

    $document = $db->fetchOne("SELECT * FROM vendor_documents WHERE id = ?", [$document_id]);

    if (!$document) {
        http_response_code(404);
        echo json_encode(['error' => 'Document not found']);
        exit;
    }

    // Permission checks
    if ($user['type'] == 'vendor' && $document['vendor_id'] != $user['account_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Can only delete your own documents']);
        exit;
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Delete file
    if (file_exists($document['file_path'])) {
        unlink($document['file_path']);
    }

    // Delete record
    $db->query("DELETE FROM vendor_documents WHERE id = ?", [$document_id]);

    $auth->logActivity($user['id'], 'delete_vendor_document', 'vendor_document', $document_id, [
        'document_type' => $document['document_type']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Document deleted successfully'
    ]);
}

function savePaymentMethodPreference($db, $user) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['vendor_id']) || empty($data['payment_method'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID and payment method required']);
        exit;
    }

    // Permission checks
    if ($user['type'] == 'vendor' && $data['vendor_id'] != $user['account_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Check if already exists
    $existing = $db->fetchOne("
        SELECT id FROM payment_method_preferences
        WHERE vendor_id = ? AND payment_method = ?
    ", [$data['vendor_id'], $data['payment_method']]);

    if ($existing) {
        // Update
        $db->query("
            UPDATE payment_method_preferences SET
                is_preferred = ?,
                account_holder_name = COALESCE(?, account_holder_name),
                bank_name = COALESCE(?, bank_name),
                wire_instructions = COALESCE(?, wire_instructions),
                notes = COALESCE(?, notes)
            WHERE id = ?
        ", [
            $data['is_preferred'] ? 1 : 0,
            $data['account_holder_name'] ?? null,
            $data['bank_name'] ?? null,
            $data['wire_instructions'] ?? null,
            $data['notes'] ?? null,
            $existing['id']
        ]);
    } else {
        // Insert
        $db->query("
            INSERT INTO payment_method_preferences (
                vendor_id, payment_method, is_preferred, account_holder_name,
                bank_name, wire_instructions, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['vendor_id'],
            $data['payment_method'],
            $data['is_preferred'] ? 1 : 0,
            $data['account_holder_name'] ?? null,
            $data['bank_name'] ?? null,
            $data['wire_instructions'] ?? null,
            $data['notes'] ?? null
        ]);
    }

    // If set as preferred, unset others
    if ($data['is_preferred']) {
        $db->query("
            UPDATE payment_method_preferences
            SET is_preferred = 0
            WHERE vendor_id = ? AND payment_method != ?
        ", [$data['vendor_id'], $data['payment_method']]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment method preference saved successfully'
    ]);
}

function getPaymentPreferences($db, $user) {
    $vendor_id = $_GET['vendor_id'] ?? null;

    // Permission checks
    if ($user['type'] == 'vendor') {
        $vendor_id = $user['account_id'];
    } elseif ($user['type'] == 'dealer') {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    if (!$vendor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID required']);
        exit;
    }

    $preferences = $db->fetchAll("
        SELECT *
        FROM payment_method_preferences
        WHERE vendor_id = ? AND is_active = 1
        ORDER BY is_preferred DESC
    ", [$vendor_id]);

    echo json_encode([
        'success' => true,
        'data' => $preferences
    ]);
}

function getVendorTermInfo($db, $user) {
    $vendor_id = $user['account_id'] ?? null;

    if (!$vendor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID required']);
        exit;
    }

    $result = $db->fetchOne("
        SELECT vp.term AS term_id, t.term, t.invoice_due_days
        FROM vendor_profiles vp
        LEFT JOIN terms t ON vp.term = t.id
        WHERE vp.vendor_id = ?
    ", [$vendor_id]);

    if (!$result || !$result['term']) {
        echo json_encode([
            'success' => false,
            'data' => [
                'invoice_due_days' => 30
            ]
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'term' => $result['term'],
            'invoice_due_days' => $result['invoice_due_days'] !== null ? (int)$result['invoice_due_days'] : 30
        ]
    ]);
}
?>