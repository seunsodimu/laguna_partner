<?php
/**
 * Email Templates API
 * Handles CRUD operations for email templates
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
if ($user['type'] !== 'user' || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin access required.']);
    exit;
}

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $action);
            break;
        case 'POST':
            handlePost($db, $action, $user);
            break;
        case 'PUT':
            handlePut($db, $action, $user);
            break;
        case 'DELETE':
            handleDelete($db, $action, $user);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet($db, $action) {
    if ($action === 'list') {
        $templates = $db->fetchAll(
            "SELECT id, name, subject, CHAR_LENGTH(body) as body_length, created_at, updated_at FROM email_templates ORDER BY name"
        );
        
        echo json_encode([
            'success' => true,
            'data' => $templates
        ]);
    } elseif ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            return;
        }
        
        $template = $db->fetchOne(
            "SELECT * FROM email_templates WHERE id = ?",
            [$id]
        );
        
        if (!$template) {
            http_response_code(404);
            echo json_encode(['error' => 'Template not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $template
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePost($db, $action, $user) {
    if ($action === 'create') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $name = trim($data['name'] ?? '');
        $subject = trim($data['subject'] ?? '');
        $body = trim($data['body'] ?? '');
        
        if (!$name || !$subject || !$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, subject, and body are required']);
            return;
        }
        
        if (strlen($name) > 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Name must be 100 characters or less']);
            return;
        }
        
        $existing = $db->fetchOne(
            "SELECT id FROM email_templates WHERE name = ?",
            [$name]
        );
        
        if ($existing) {
            http_response_code(400);
            echo json_encode(['error' => 'Template with this name already exists']);
            return;
        }
        
        try {
            $db->query(
                "INSERT INTO email_templates (name, subject, body) VALUES (?, ?, ?)",
                [$name, $subject, $body]
            );
            
            $templateId = $db->getConnection()->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Email template created successfully',
                'data' => [
                    'id' => $templateId,
                    'name' => $name,
                    'subject' => $subject
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create template: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePut($db, $action, $user) {
    if ($action === 'update') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            return;
        }
        
        $template = $db->fetchOne(
            "SELECT * FROM email_templates WHERE id = ?",
            [$id]
        );
        
        if (!$template) {
            http_response_code(404);
            echo json_encode(['error' => 'Template not found']);
            return;
        }
        
        $name = isset($data['name']) ? trim($data['name']) : $template['name'];
        $subject = isset($data['subject']) ? trim($data['subject']) : $template['subject'];
        $body = isset($data['body']) ? trim($data['body']) : $template['body'];
        
        if (!$name || !$subject || !$body) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, subject, and body are required']);
            return;
        }
        
        if ($name !== $template['name']) {
            if (strlen($name) > 100) {
                http_response_code(400);
                echo json_encode(['error' => 'Name must be 100 characters or less']);
                return;
            }
            
            $existing = $db->fetchOne(
                "SELECT id FROM email_templates WHERE name = ? AND id != ?",
                [$name, $id]
            );
            
            if ($existing) {
                http_response_code(400);
                echo json_encode(['error' => 'Template with this name already exists']);
                return;
            }
        }
        
        try {
            $db->query(
                "UPDATE email_templates SET name = ?, subject = ?, body = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                [$name, $subject, $body, $id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Email template updated successfully',
                'data' => [
                    'id' => $id,
                    'name' => $name,
                    'subject' => $subject
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update template: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

function handleDelete($db, $action, $user) {
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            return;
        }
        
        $template = $db->fetchOne(
            "SELECT * FROM email_templates WHERE id = ?",
            [$id]
        );
        
        if (!$template) {
            http_response_code(404);
            echo json_encode(['error' => 'Template not found']);
            return;
        }
        
        try {
            $db->query(
                "DELETE FROM email_templates WHERE id = ?",
                [$id]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Email template deleted successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete template: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}
