<?php
/**
 * Test Sync Endpoint - Debug version
 */

// Suppress ALL errors from displaying
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Start output buffering FIRST
ob_start();

// Set JSON header
header('Content-Type: application/json');

try {
    echo json_encode([
        'step' => 'Starting',
        'success' => true
    ]);
    ob_end_flush();
    exit;
    
    // Step 1: Load autoload
    require_once __DIR__ . '/../../vendor/autoload.php';
    ob_clean();
    echo json_encode(['step' => 'Autoload loaded', 'success' => true]);
    ob_end_flush();
    exit;
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    ob_end_flush();
}