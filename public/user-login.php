<?php
/**
 * User Login Page
 * Unified login for internal users (Buyer, Accounting, Admin roles)
 * OTP-based authentication
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/EmailService.php';

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
    } elseif ($user['type'] === 'vendor') {
        header('Location: ' . BASE_PATH . '/vendor/dashboard.php');
    } elseif ($user['type'] === 'dealer') {
        header('Location: ' . BASE_PATH . '/dealer/dashboard.php');
    }
    exit;
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 'email';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'send_otp') {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            $auth = new Auth();
            $result = $auth->generateOTP($email, 'user');
            
            if ($result['success']) {
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_user_type'] = 'user';
                $success = $result['message'];
                $step = 'otp';
            } else {
                $error = $result['message'];
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $email = $_SESSION['otp_email'] ?? '';
        $otp = $_POST['otp'] ?? '';

        if (empty($otp)) {
            $error = 'Please enter the OTP code';
        } else {
            $auth = new Auth();
            $result = $auth->verifyOTP($email, 'user', $otp);
            
            if ($result['success']) {
                unset($_SESSION['otp_email']);
                unset($_SESSION['otp_user_type']);
                
                // Redirect based on role
                $user = $result['user'];
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
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - Laguna Partners Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
        }
        .portal-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="bi bi-shield-lock"></i> Laguna Partners Portal</h2>
                <p class="mb-0">Internal User Access</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'email'): ?>
                    <!-- Step 1: Enter Email -->
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="send_otp">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Enter your email" required autofocus>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-envelope"></i> Send Login Code
                        </button>
                    </form>

                    <div class="portal-link">
                        <small class="text-muted">Looking for Vendor or Dealer Login?</small><br>
                        <a href="<?= BASE_PATH; ?>/vendor-login.php" class="btn btn-outline-secondary btn-sm mt-2">
                            <i class="bi bi-arrow-right"></i> Vendor Login
                        </a>
                        <a href="<?= BASE_PATH; ?>/dealer-login.php" class="btn btn-outline-secondary btn-sm mt-2">
                            <i class="bi bi-arrow-right"></i> Dealer Login
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Step 2: Enter OTP -->
                    <div class="text-center mb-4">
                        <i class="bi bi-envelope-check" style="font-size: 3rem; color: #1e3c72;"></i>
                        <h5 class="mt-3">Check Your Email</h5>
                        <p class="text-muted">We've sent a 6-digit code to<br><strong><?= htmlspecialchars($_SESSION['otp_email'] ?? '') ?></strong></p>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="verify_otp">
                        
                        <div class="mb-3">
                            <label for="otp" class="form-label">Enter Code</label>
                            <input type="text" class="form-control text-center" id="otp" name="otp" 
                                   placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus
                                   style="font-size: 1.5rem; letter-spacing: 0.5rem;">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="bi bi-box-arrow-in-right"></i> Verify & Login
                        </button>

                        <a href="<?php echo BASE_PATH; ?>/user-login.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </form>

                    <div class="text-center mt-3">
                        <small class="text-muted">Code expires in 15 minutes</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
