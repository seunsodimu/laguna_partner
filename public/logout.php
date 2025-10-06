<?php
/**
 * Logout Page
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Auth.php';

use LagunaPartners\Auth;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Define base path for redirects
define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

Auth::logout();

header('Location: ' . BASE_PATH . '/index.php');
exit;