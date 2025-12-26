<?php
/**
 * Email API
 * Handles email operations like test sending
 */

require_once '../../config/config.php';
require_once '../../src/Database.php';
require_once '../../src/Auth.php';
require_once '../../src/EmailService.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;
use LagunaPartners\EmailService;

header('Content-Type: application/json');

session_start();

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = Auth::user();
if ($user['type'] !== 'user' || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin access required.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            handlePost($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handlePost($action) {
    if ($action === 'test') {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = trim($data['email'] ?? '');

        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Email address is required']);
            return;
        }

        try {
            $emailService = new EmailService();
            $result = $emailService->testEmailSend($email);

            if ($result['success']) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'provider' => $result['provider'],
                    'timestamp' => $result['timestamp']
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message']
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Exception occurred',
                'message' => $e->getMessage()
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}
