<?php
/**
 * Authentication Class
 * Handles OTP generation, validation, and session management
 */

namespace LagunaPartners;

class Auth {
    private $db;
    private $email;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->email = new EmailService();
    }

    /**
     * Generate and send OTP to user
     */
    public function generateOTP($email, $userType) {
        // Validate user exists and has access
        if (!$this->validateUserAccess($email, $userType)) {
            return [
                'success' => false,
                'message' => 'User not found or does not have access to this portal'
            ];
        }

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Store OTP in database
        $this->db->insert('otp_codes', [
            'email' => $email,
            'user_type' => $userType,
            'code' => $otp,
            'expires_at' => $expiresAt,
            'is_used' => 0
        ]);

        // Send OTP via email
        $sent = $this->email->sendOTP($email, $otp);

        if ($sent) {
            return [
                'success' => true,
                'message' => 'OTP sent to your email address'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.'
            ];
        }
    }

    /**
     * Validate user has access to portal
     * Note: userType can be 'user' (internal staff), 'vendor', or 'dealer'
     */
    private function validateUserAccess($email, $userType) {
        if ($userType === 'user') {
            // Check if internal user exists in users table with active status
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE email = ? AND type = 'user' AND is_active =1",
                [$email]
            );
            return $user !== false;
        } elseif ($userType === 'vendor' || $userType === 'dealer') {
            // Check if email exists in accounts or user_accounts
            $sql = "SELECT a.* FROM accounts a 
                    LEFT JOIN user_accounts ua ON a.id = ua.account_id
                    LEFT JOIN users u ON ua.user_id = u.id
                    WHERE a.type = ? AND a.is_active = 1
                    AND (a.email = ? OR u.email = ?)";
            $account = $this->db->fetchOne($sql, [$userType, $email, $email]);
            return $account !== false;
        }

        return false;
    }

    /**
     * Verify OTP and create session
     */
    public function verifyOTP($email, $userType, $otp) {
        // Find valid OTP
        $sql = "SELECT * FROM otp_codes 
                WHERE email = ? AND user_type = ? AND code = ? 
                AND is_used = 0 AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1";
        
        $otpRecord = $this->db->fetchOne($sql, [$email, $userType, $otp]);

        if (!$otpRecord) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ];
        }

        // Mark OTP as used
        $this->db->update('otp_codes', 
            ['is_used' => 1],
            'id = ?',
            [$otpRecord['id']]
        );

        // Get or create user
        $user = $this->getOrCreateUser($email, $userType);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Failed to create user session'
            ];
        }

        // Update last login
        $this->db->update('users',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$user['id']]
        );

        // Create session
        $this->createSession($user);
        
        // For dealers and vendors, set the active account
        if ($userType === 'dealer' || $userType === 'vendor') {
            $this->setActiveAccount($user['id'], $email, $userType);
        }

        // Log activity
        $this->logActivity($user['id'], 'login', null, [
            'user_type' => $userType,
            'email' => $email
        ]);

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $user
        ];
    }

    /**
     * Get or create user record
     */
    private function getOrCreateUser($email, $userType) {
        // Check if user exists
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND type = ?",
            [$email, $userType]
        );

        if ($user) {
            return $user;
        }

        // Create new user
        $userId = $this->db->insert('users', [
            'email' => $email,
            'type' => $userType,
            'is_active' => 1
        ]);

        return $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
    }

    /**
     * Create user session
     */
    private function createSession($user) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_type'] = $user['type'];
        $_SESSION['role'] = $user['role'] ?? null;
        $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
    }
    
    /**
     * Set active account for dealers/vendors
     */
    private function setActiveAccount($userId, $email, $userType) {
        // First, try to find account directly by email
        $sql = "SELECT id FROM accounts 
                WHERE type = ? AND email = ? AND is_active = 1
                LIMIT 1";
        
        $account = $this->db->fetchOne($sql, [$userType, $email]);
        
        // If not found, try through user_accounts relationship
        if (!$account) {
            $sql = "SELECT a.id FROM accounts a 
                    INNER JOIN user_accounts ua ON a.id = ua.account_id
                    WHERE ua.user_id = ? AND a.type = ? AND a.is_active = 1
                    ORDER BY ua.is_primary DESC
                    LIMIT 1";
            
            $account = $this->db->fetchOne($sql, [$userId, $userType]);
        }
        
        if ($account) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['active_account_id'] = $account['id'];
            
            // Create user_accounts relationship if it doesn't exist
            $existingRelation = $this->db->fetchOne(
                "SELECT id FROM user_accounts WHERE user_id = ? AND account_id = ?",
                [$userId, $account['id']]
            );
            
            if (!$existingRelation) {
                $this->db->insert('user_accounts', [
                    'user_id' => $userId,
                    'account_id' => $account['id'],
                    'is_primary' => 1
                ]);
            }
        }
    }

    /**
     * Check if user is authenticated
     */
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Get current user
     */
    public static function user() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!self::check()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'type' => $_SESSION['user_type'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'account_id' => $_SESSION['active_account_id'] ?? null
        ];
    }

    /**
     * Logout user
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Log activity before destroying session
        if (isset($_SESSION['user_id'])) {
            $db = Database::getInstance();
            $db->insert('user_logs', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'logout',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        }

        session_destroy();
        session_unset();
    }

    /**
     * Get user accounts (for vendors/dealers with multiple accounts)
     */
    public function getUserAccounts($userId) {
        $sql = "SELECT a.*, ua.is_primary 
                FROM accounts a
                INNER JOIN user_accounts ua ON a.id = ua.account_id
                WHERE ua.user_id = ? AND a.is_active = 1
                ORDER BY ua.is_primary DESC, a.company_name ASC";
        
        return $this->db->fetchAll($sql, [$userId]);
    }

    /**
     * Switch active account
     */
    public function switchAccount($userId, $accountId) {
        // Verify user has access to this account
        $sql = "SELECT * FROM user_accounts WHERE user_id = ? AND account_id = ?";
        $access = $this->db->fetchOne($sql, [$userId, $accountId]);

        if (!$access) {
            return false;
        }

        // Update session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['active_account_id'] = $accountId;

        return true;
    }

    /**
     * Log user activity
     */
    public static function logActivity($userId, $action, $entityType = null, $details = []) {
        $db = Database::getInstance();
        
        // Extract entity_id from details if present
        $entityId = null;
        if (is_array($details)) {
            $entityId = $details['entity_id'] ?? null;
            unset($details['entity_id']);
        }
        
        $db->insert('user_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => is_array($details) ? json_encode($details) : $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Require authentication
     */
    public static function requireAuth($allowedTypes = []) {
        if (!self::check()) {
            header('Location: /login.php');
            exit;
        }

        if (!empty($allowedTypes)) {
            $user = self::user();
            if (!in_array($user['type'], $allowedTypes)) {
                header('HTTP/1.1 403 Forbidden');
                echo "Access denied";
                exit;
            }
        }
    }
}