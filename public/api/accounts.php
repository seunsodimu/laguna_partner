<?php
/**
 * Accounts API
 * Handles vendor and dealer account operations
 */

require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

header('Content-Type: application/json');

$action = $_GET['action'] ?? null;
$type = $_GET['type'] ?? null;

try {
    switch ($action) {
        case null:
            handleGetAccounts($type);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }

} catch (\Exception $e) {
    http_response_code(500);
    error_log("Accounts API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

function handleGetAccounts($type) {
    $db = Database::getInstance();
    
    if (!$type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Type parameter required']);
        return;
    }

    $validTypes = ['vendor', 'dealer'];
    if (!in_array($type, $validTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid type']);
        return;
    }

    try {
        $accounts = $db->fetchAll(
            "SELECT id, company_name, email, is_active FROM accounts WHERE type = ? AND is_active = 1 ORDER BY company_name",
            [$type]
        );
        
        echo json_encode(['success' => true, 'data' => $accounts]);
    } catch (\Exception $e) {
        error_log("Error getting accounts: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
}
?>
