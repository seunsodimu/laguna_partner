<?php
/**
 * Teams Webhook Configuration API
 * Handles configuration of Microsoft Teams webhooks for notifications
 */

require_once '../../config/config.php';
require_once '../../src/Database.php';
require_once '../../src/Auth.php';
require_once '../../src/TeamsService.php';

use LagunaPartners\Database;
use LagunaPartners\Auth;
use LagunaPartners\TeamsService;

header('Content-Type: application/json');

session_start();

// Verify user is authenticated and is an admin
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
            handleDelete($db, $action);
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
        // Get all webhook configurations
        $configs = $db->fetchAll(
            "SELECT id, notification_type, channel_name, is_active, description, created_at, updated_at FROM teams_webhook_config ORDER BY notification_type"
        );
        
        echo json_encode([
            'success' => true,
            'data' => $configs
        ]);
    } elseif ($action === 'get') {
        // Get single webhook configuration
        $id = intval($_GET['id'] ?? 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            return;
        }
        
        $config = $db->fetchOne(
            "SELECT * FROM teams_webhook_config WHERE id = ?",
            [$id]
        );
        
        if (!$config) {
            http_response_code(404);
            echo json_encode(['error' => 'Configuration not found']);
            return;
        }
        
        // Don't expose webhook URL in detail view
        unset($config['webhook_url']);
        
        echo json_encode([
            'success' => true,
            'data' => $config
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePost($db, $action, $user) {
    if ($action === 'test') {
        // Test a webhook URL
        $data = json_decode(file_get_contents('php://input'), true);
        $webhookUrl = $data['webhook_url'] ?? null;
        
        if (!$webhookUrl) {
            http_response_code(400);
            echo json_encode(['error' => 'Webhook URL required']);
            return;
        }
        
        $teamsService = new TeamsService();
        $result = $teamsService->testWebhook($webhookUrl);
        
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePut($db, $action, $user) {
    if ($action === 'update') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $id = intval($data['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            return;
        }
        
        // Validate required fields
        $webhookUrl = $data['webhook_url'] ?? null;
        $channelId = $data['channel_id'] ?? null;
        $channelName = $data['channel_name'] ?? null;
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : null;
        
        if (!$webhookUrl) {
            http_response_code(400);
            echo json_encode(['error' => 'Webhook URL is required']);
            return;
        }
        
        // Validate webhook URL format
        if (!filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid webhook URL format']);
            return;
        }
        
        // Check if configuration exists
        $existing = $db->fetchOne(
            "SELECT * FROM teams_webhook_config WHERE id = ?",
            [$id]
        );
        
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'Configuration not found']);
            return;
        }
        
        // Update configuration
        $updateFields = [];
        $params = [];
        
        if ($webhookUrl) {
            $updateFields[] = "webhook_url = ?";
            $params[] = $webhookUrl;
        }
        
        if ($channelId) {
            $updateFields[] = "channel_id = ?";
            $params[] = $channelId;
        }
        
        if ($channelName) {
            $updateFields[] = "channel_name = ?";
            $params[] = $channelName;
        }
        
        if ($isActive !== null) {
            $updateFields[] = "is_active = ?";
            $params[] = $isActive;
        }
        
        $updateFields[] = "updated_at = NOW()";
        $updateFields[] = "updated_by_user_id = ?";
        $params[] = $user['id'];
        $params[] = $id;
        
        $sql = "UPDATE teams_webhook_config SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $db->query($sql, $params);
        
        // Log activity
        Auth::logActivity($user['id'], 'update_teams_webhook', 'teams_webhook_config', [
            'webhook_id' => $id,
            'channel_name' => $channelName
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Webhook configuration updated successfully'
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

function handleDelete($db, $action) {
    http_response_code(400);
    echo json_encode(['error' => 'Delete operation not permitted']);
}
