<?php
/**
 * Users API
 * 
 * Handles user management operations (admin only)
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;

header('Content-Type: application/json');
session_start();

$action = $_GET['action'] ?? null;

// Allow public access to get_team_members for messaging
if ($action === 'get_team_members') {
    handleGetTeamMembers();
    exit;
}

// Check authentication and admin access for other operations
if (!Auth::check() || !Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$db = Database::getInstance();
$user = Auth::user();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
            
        case 'POST':
            handlePost($db, $user);
            break;
            
        case 'PUT':
            handlePut($db, $user);
            break;
            
        case 'DELETE':
            handleDelete($db, $user);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Users API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

function handleGet($db) {
    $userId = $_GET['id'] ?? null;
    $include = $_GET['include'] ?? '';
    
    if ($userId) {
        // Get single user
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$userId]
        );
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        
        // Include related data if requested
        if ($include === 'accounts') {
            $user['accounts'] = $db->fetchAll(
                "SELECT a.* FROM accounts a
                 JOIN user_accounts ua ON a.id = ua.account_id
                 WHERE ua.user_id = ?
                 ORDER BY a.company_name",
                [$userId]
            );
            echo json_encode($user);
            return;
        }
        
        if ($include === 'logs') {
            $user['logs'] = $db->fetchAll(
                "SELECT * FROM user_logs
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT 100",
                [$userId]
            );
            echo json_encode($user);
            return;
        }
        
        echo json_encode($user);
    } else {
        // Get all users
        $users = $db->fetchAll(
            "SELECT u.*, 
                    COUNT(DISTINCT ua.account_id) as account_count
             FROM users u
             LEFT JOIN user_accounts ua ON u.id = ua.user_id
             GROUP BY u.id
             ORDER BY u.created_at DESC"
        );
        
        echo json_encode($users);
    }
}

function handlePost($db, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['email']) || empty($data['type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and type are required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Validate user type
    $validTypes = ['admin', 'buyer', 'vendor', 'dealer'];
    if (!in_array($data['type'], $validTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user type']);
        return;
    }
    
    // Check if email already exists
    $existing = $db->fetchOne(
        "SELECT id FROM users WHERE email = ?",
        [$data['email']]
    );
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        return;
    }
    
    // Insert user
    $db->query(
        "INSERT INTO users (email, first_name, last_name, type, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, 1, NOW(), NOW())",
        [
            $data['email'],
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['type']
        ]
    );
    
    $newUserId = $db->lastInsertId();
    
    // Log the action
    $db->query(
        "INSERT INTO user_logs (user_id, action, details, ip_address, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [
            $currentUser['id'],
            'create_user',
            json_encode([
                'new_user_id' => $newUserId,
                'email' => $data['email'],
                'type' => $data['type']
            ]),
            $_SERVER['REMOTE_ADDR']
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'User created successfully',
        'user_id' => $newUserId
    ]);
}

function handlePut($db, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    // Get existing user
    $existingUser = $db->fetchOne(
        "SELECT * FROM users WHERE id = ?",
        [$data['id']]
    );
    
    if (!$existingUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Prevent admin from deactivating themselves
    if (isset($data['is_active']) && $data['id'] == $currentUser['id'] && !$data['is_active']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot deactivate your own account']);
        return;
    }
    
    // Build update query
    $updates = [];
    $params = [];
    
    if (isset($data['email'])) {
        // Check if new email already exists
        $emailCheck = $db->fetchOne(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            [$data['email'], $data['id']]
        );
        
        if ($emailCheck) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
        
        $updates[] = "email = ?";
        $params[] = $data['email'];
    }
    
    if (isset($data['first_name'])) {
        $updates[] = "first_name = ?";
        $params[] = $data['first_name'];
    }
    
    if (isset($data['last_name'])) {
        $updates[] = "last_name = ?";
        $params[] = $data['last_name'];
    }
    
    if (isset($data['type'])) {
        $validTypes = ['admin', 'buyer', 'vendor', 'dealer'];
        if (!in_array($data['type'], $validTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user type']);
            return;
        }
        $updates[] = "type = ?";
        $params[] = $data['type'];
    }
    
    if (isset($data['is_active'])) {
        $updates[] = "is_active = ?";
        $params[] = $data['is_active'] ? 1 : 0;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $data['id'];
    
    // Update user
    $db->query(
        "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?",
        $params
    );
    
    // Log the action
    $db->query(
        "INSERT INTO user_logs (user_id, action, details, ip_address, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [
            $currentUser['id'],
            'update_user',
            json_encode([
                'updated_user_id' => $data['id'],
                'changes' => array_intersect_key($data, array_flip(['email', 'first_name', 'last_name', 'type', 'is_active']))
            ]),
            $_SERVER['REMOTE_ADDR']
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
}

function handleDelete($db, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    // Prevent admin from deleting themselves
    if ($data['id'] == $currentUser['id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Check if user exists
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE id = ?",
        [$data['id']]
    );
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Instead of deleting, deactivate the user (soft delete)
    $db->query(
        "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?",
        [$data['id']]
    );
    
    // Log the action
    $db->query(
        "INSERT INTO user_logs (user_id, action, details, ip_address, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [
            $currentUser['id'],
            'delete_user',
            json_encode([
                'deleted_user_id' => $data['id'],
                'email' => $user['email']
            ]),
            $_SERVER['REMOTE_ADDR']
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'User deactivated successfully'
    ]);
}

function handleGetTeamMembers() {
    $db = Database::getInstance();
    $type = $_GET['type'] ?? null;
    
    if (!$type) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Type parameter required']);
        return;
    }
    
    $typeMap = [
        'vendor_to_accounting' => 'buyer',
        'vendor_to_buyer' => 'buyer'
    ];
    
    $userType = $typeMap[$type] ?? null;
    
    if (!$userType) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid type']);
        return;
    }
    
    try {
        $users = $db->fetchAll(
            "SELECT id, first_name, last_name, email FROM users WHERE type = ? AND is_active = 1 ORDER BY first_name, last_name",
            [$userType]
        );
        
        echo json_encode(['success' => true, 'data' => $users]);
    } catch (\Exception $e) {
        error_log("Error getting team members: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
}