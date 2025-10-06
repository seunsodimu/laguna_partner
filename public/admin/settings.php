<?php
/**
 * Admin Settings
 * Manage system settings including NetSuite environment
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Auth.php';

use LagunaPartners\Auth;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Define base path for redirects
define('BASE_PATH', $_ENV['APP_BASE_PATH'] ?? '/laguna_partner');

session_start();

// Check authentication and user type
if (!Auth::check()) {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

$user = Auth::user();
if ($user['type'] !== 'admin') {
    header('Location: ' . BASE_PATH . '/index.php');
    exit;
}

// Load current environment
$envFile = __DIR__ . '/../../.env';
$currentEnv = 'sandbox'; // default

if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/NETSUITE_ENVIRONMENT=(\w+)/', $envContent, $matches)) {
        $currentEnv = $matches[1];
    }
}

// Handle environment switch
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_environment'])) {
    $newEnv = $_POST['environment'] ?? 'sandbox';
    
    if (in_array($newEnv, ['production', 'sandbox'])) {
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            $envContent = preg_replace(
                '/NETSUITE_ENVIRONMENT=\w+/',
                'NETSUITE_ENVIRONMENT=' . $newEnv,
                $envContent
            );
            
            if (file_put_contents($envFile, $envContent)) {
                $currentEnv = $newEnv;
                $message = "Successfully switched to NetSuite $newEnv environment";
                $messageType = 'success';
                
                // Log the change
                error_log("NetSuite environment switched to: $newEnv by user: " . $_SESSION['user_email']);
            } else {
                $message = "Failed to update .env file. Check file permissions.";
                $messageType = 'danger';
            }
        } else {
            $message = ".env file not found";
            $messageType = 'danger';
        }
    } else {
        $message = "Invalid environment selection";
        $messageType = 'danger';
    }
}

// Load credentials to show current configuration
$credentials = require __DIR__ . '/../../config/credentials.php';
$currentConfig = $credentials['netsuite'][$currentEnv] ?? null;

$pageTitle = 'System Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2 class="mb-4">System Settings</h2>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- NetSuite Environment Settings -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-cloud"></i> NetSuite Environment</h5>
                </div>
                <div class="card-body">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to switch NetSuite environments? This will affect all API calls immediately.');">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Environment</label>
                            <div class="alert alert-<?php echo $currentEnv === 'production' ? 'danger' : 'info'; ?>">
                                <i class="bi bi-<?php echo $currentEnv === 'production' ? 'exclamation-triangle' : 'info-circle'; ?>"></i>
                                <strong><?php echo strtoupper($currentEnv); ?></strong>
                                <?php if ($currentEnv === 'production'): ?>
                                <br><small>⚠️ You are connected to the PRODUCTION NetSuite environment. All changes will affect live data.</small>
                                <?php else: ?>
                                <br><small>You are connected to the SANDBOX NetSuite environment. Safe for testing.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($currentConfig): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Configuration</label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <tr>
                                        <th width="30%">Account ID</th>
                                        <td><code><?php echo htmlspecialchars($currentConfig['account_id']); ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>Base URL</th>
                                        <td><code><?php echo htmlspecialchars($currentConfig['rest_url']); ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>Consumer Key</th>
                                        <td><code><?php echo substr($currentConfig['consumer_key'], 0, 20) . '...'; ?></code></td>
                                    </tr>
                                    <tr>
                                        <th>Token ID</th>
                                        <td><code><?php echo substr($currentConfig['token_id'], 0, 20) . '...'; ?></code></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Switch Environment</label>
                            <select name="environment" class="form-select" required>
                                <option value="sandbox" <?php echo $currentEnv === 'sandbox' ? 'selected' : ''; ?>>
                                    Sandbox (Testing)
                                </option>
                                <option value="production" <?php echo $currentEnv === 'production' ? 'selected' : ''; ?>>
                                    Production (Live)
                                </option>
                            </select>
                            <div class="form-text">
                                Select the NetSuite environment to connect to. Changes take effect immediately.
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="switch_environment" class="btn btn-primary btn-lg">
                                <i class="bi bi-arrow-repeat"></i> Switch Environment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Environment Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Environment Information</h5>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold">Production Environment</h6>
                    <ul>
                        <li>Account ID: <code>11134099</code></li>
                        <li>URL: <code>https://11134099.suitetalk.api.netsuite.com</code></li>
                        <li><span class="badge bg-danger">⚠️ LIVE DATA</span> - All changes affect production</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">Sandbox Environment</h6>
                    <ul>
                        <li>Account ID: <code>11134099_SB1</code></li>
                        <li>URL: <code>https://11134099-sb1.suitetalk.api.netsuite.com</code></li>
                        <li><span class="badge bg-success">✓ SAFE</span> - Test environment for development</li>
                    </ul>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Important:</strong> After switching environments, you should run a full sync to ensure data consistency.
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="/admin/dashboard.php" class="btn btn-outline-primary w-100 mb-2">
                        <i class="bi bi-speedometer2"></i> Back to Dashboard
                    </a>
                    <a href="/admin/dashboard.php#sync" class="btn btn-outline-success w-100">
                        <i class="bi bi-arrow-repeat"></i> Run Manual Sync
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>