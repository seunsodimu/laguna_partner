<?php
/**
 * Portal Landing Page
 * Directs users to appropriate login pages
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';

use LagunaPartners\Auth;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Define base path for redirects
define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

session_start();

// Redirect if already logged in
if (Auth::check()) {
    $user = Auth::user();
    if ($user['type'] === 'user') {
        // Route based on role
        $role = $user['role'] ?? 'buyer';
        switch ($role) {
            case 'admin':
                header('Location: ' . BASE_PATH . '/admin/dashboard.php');
                break;
            case 'accounting':
            case 'buyer':
            default:
                header('Location: ' . BASE_PATH . '/buyer/dashboard.php');
                break;
        }
    } else {
        // Route for vendor/dealer
        switch ($user['type']) {
            case 'vendor':
                header('Location: ' . BASE_PATH . '/vendor/dashboard.php');
                break;
            case 'dealer':
                header('Location: ' . BASE_PATH . '/dealer/dashboard.php');
                break;
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Laguna Partners Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .portal-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 900px;
            width: 100%;
        }
        .portal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .portal-body {
            padding: 40px;
        }
        .login-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .login-option:hover {
            border-color: #667eea;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
            transform: translateY(-5px);
        }
        .login-option-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .admin-buyer-gradient {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .vendor-dealer-gradient {
            background: linear-gradient(135deg, #134e5e 0%, #71b280 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .btn-admin-buyer {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
            color: white;
        }
        .btn-admin-buyer:hover {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
            color: white;
        }
        .btn-vendor-dealer {
            background: linear-gradient(135deg, #134e5e 0%, #71b280 100%);
            border: none;
            color: white;
        }
        .btn-vendor-dealer:hover {
            background: linear-gradient(135deg, #71b280 0%, #134e5e 100%);
            color: white;
        }
        .user-type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            margin: 3px;
            font-weight: 500;
        }
        .badge-admin {
            background: #dc3545;
            color: white;
        }
        .badge-buyer {
            background: #0dcaf0;
            color: white;
        }
        .badge-vendor {
            background: #198754;
            color: white;
        }
        .badge-dealer {
            background: #fd7e14;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="portal-card">
            <div class="portal-header">
                <h1><i class="bi bi-shield-lock"></i> Laguna Partners Portal</h1>
                <p class="mb-0 fs-5">Welcome back! Please select your login portal now</p>
            </div>
            <div class="portal-body">
                <div class="row g-4">
                    <!-- Internal User Login -->
                    <div class="col-md-4">
                        <div class="login-option">
                            <div>
                                <div class="login-option-icon admin-buyer-gradient">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <h3 class="admin-buyer-gradient mb-3">Internal User</h3>
                                <p class="text-muted mb-3">
                                    Access for internal staff with various roles
                                </p>
                                <div class="mb-4">
                                    <span class="user-type-badge badge-admin">
                                        <i class="bi bi-gear-fill"></i> Admin
                                    </span>
                                    <span class="user-type-badge badge-buyer">
                                        <i class="bi bi-cart-fill"></i> Buyer
                                    </span>
                                    <span class="user-type-badge badge-buyer">
                                        <i class="bi bi-file-earmark-text"></i> Accounting
                                    </span>
                                </div>
                            </div>
                            <a href="<?php echo BASE_PATH; ?>/user-login.php" class="btn btn-admin-buyer btn-lg w-100">
                                <i class="bi bi-box-arrow-in-right"></i> User Login
                            </a>
                        </div>
                    </div>

                    <!-- Vendor Login -->
                    <div class="col-md-4">
                        <div class="login-option">
                            <div>
                                <div class="login-option-icon vendor-dealer-gradient">
                                    <i class="bi bi-building"></i>
                                </div>
                                <h3 class="vendor-dealer-gradient mb-3">Vendor</h3>
                                <p class="text-muted mb-3">
                                    Access vendor portal
                                </p>
                                <div class="mb-4">
                                    <span class="user-type-badge badge-vendor">
                                        <i class="bi bi-building"></i> Vendor
                                    </span>
                                </div>
                            </div>
                            <a href="<?php echo BASE_PATH; ?>/vendor-login.php" class="btn btn-vendor-dealer btn-lg w-100">
                                <i class="bi bi-box-arrow-in-right"></i> Vendor Login
                            </a>
                        </div>
                    </div>

                    <!-- Dealer Login -->
                    <div class="col-md-4">
                        <div class="login-option">
                            <div>
                                <div class="login-option-icon" style="background: linear-gradient(135deg, #d6663c 0%, #f5a962 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                                    <i class="bi bi-shop"></i>
                                </div>
                                <h3 style="background: linear-gradient(135deg, #d6663c 0%, #f5a962 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 1rem;">Dealer</h3>
                                <p class="text-muted mb-3">
                                    Access dealer portal
                                </p>
                                <div class="mb-4">
                                    <span class="user-type-badge badge-dealer">
                                        <i class="bi bi-shop"></i> Dealer
                                    </span>
                                </div>
                            </div>
                            <a href="<?php echo BASE_PATH; ?>/dealer-login.php" class="btn btn-lg w-100" style="background: linear-gradient(135deg, #d6663c 0%, #f5a962 100%); border: none; color: white;">
                                <i class="bi bi-box-arrow-in-right"></i> Dealer Login
                            </a>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4 pt-4 border-top">
                    <p class="text-muted mb-0">
                        <i class="bi bi-shield-check"></i> Secure OTP-based authentication
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>