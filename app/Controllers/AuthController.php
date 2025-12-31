<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\AnalyticsService;

class AuthController {
    private $auth;

    public function __construct() {
        try {
            $this->auth = new AuthService();
        } catch (\Exception $e) {
            // Log the full error details for debugging
            error_log("AuthController constructor error: " . $e->getMessage());
            error_log("AuthController constructor error trace: " . $e->getTraceAsString());
            
            // Check if it's a JWT library issue
            if (strpos($e->getMessage(), 'Firebase JWT') !== false) {
                throw new \Exception('Authentication service initialization failed: Firebase JWT library not found. Please run "composer install" on the server.');
            }
            
            // Check if it's a database issue
            if (strpos($e->getMessage(), 'Database connection') !== false) {
                throw new \Exception('Authentication service initialization failed: Database connection error. Please check database configuration.');
            }
            
            // Generic error
            throw new \Exception('Authentication service initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Form-based login endpoint (pure PHP)
     * POST /login
     */
    public function loginForm() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get form data and trim to remove whitespace
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $redirectUrl = $_GET['redirect'] ?? '/dashboard';
        
        // Validate inputs
        if (empty($username) || empty($password)) {
            $error = urlencode('Username and password are required');
            header('Location: ' . BASE_URL_PATH . '/?error=' . $error);
            exit;
        }
        
        try {
            // Authenticate user
            if (!$this->auth) {
                throw new \Exception('Authentication service not available');
            }
            
            $result = $this->auth->login($username, $password);
            
            // Set token in session for server-side authentication
            $_SESSION['token'] = $result['token'];
            $_SESSION['user'] = [
                'id' => $result['user']['id'],
                'username' => $result['user']['username'],
                'email' => $result['user']['email'] ?? null,
                'full_name' => $result['user']['full_name'] ?? null,
                'role' => $result['user']['role'],
                'company_id' => $result['user']['company_id'] ?? null,
                'company_name' => $result['user']['company_name'] ?? null
            ];
            
            // Set initial last activity time for session timeout tracking
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time(); // Store login time for session duration calculation
            
            // Set JWT token in cookie for web page authentication
            // Use same path as session cookie
            $cookiePath = defined('BASE_URL_PATH') && !empty(BASE_URL_PATH) ? BASE_URL_PATH : '/';
            $cookieExpire = time() + (24 * 60 * 60);
            setcookie('sellapp_token', $result['token'], $cookieExpire, $cookiePath, '', false, true);
            setcookie('token', $result['token'], $cookieExpire, $cookiePath, '', false, true);
            
            // Log audit event for login
            try {
                $auditService = new \App\Services\AuditService();
                $auditService->logEvent(
                    $result['user']['company_id'] ?? null,
                    $result['user']['id'],
                    'user.login',
                    'user',
                    $result['user']['id'],
                    [
                        'username' => $result['user']['username'],
                        'role' => $result['user']['role'],
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ],
                    $_SERVER['REMOTE_ADDR'] ?? null
                );
            } catch (\Exception $auditError) {
                // Don't fail login if audit logging fails
                error_log("Audit logging error (non-fatal): " . $auditError->getMessage());
            }
            
            // Log user activity for login
            try {
                $activityLog = new \App\Models\UserActivityLog();
                $activityLog->logLogin(
                    $result['user']['id'],
                    $result['user']['company_id'] ?? null,
                    $result['user']['role'],
                    $result['user']['username'],
                    $result['user']['full_name'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
            } catch (\Exception $activityError) {
                // Don't fail login if activity logging fails
                error_log("User activity logging error (non-fatal): " . $activityError->getMessage());
            }
            
            // Write session data before redirect
            // Ensure session is saved
            session_write_close();
            
            // Redirect to dashboard - ensure redirect URL is properly formatted
            if (empty($redirectUrl) || $redirectUrl === '/') {
                $redirectUrl = '/dashboard';
            }
            // Ensure redirect URL starts with /
            if ($redirectUrl[0] !== '/') {
                $redirectUrl = '/' . $redirectUrl;
            }
            
            $redirectPath = BASE_URL_PATH . $redirectUrl;
            header('Location: ' . $redirectPath);
            exit;
            
        } catch (\Exception $e) {
            // Log the error
            error_log("Login error: " . $e->getMessage());
            
            // Redirect back to login with error message
            $error = urlencode($e->getMessage());
            header('Location: ' . BASE_URL_PATH . '/?error=' . $error);
            exit;
        }
    }

    /**
     * API Login endpoint (JSON)
     * POST /api/auth/login
     */
    public function login() {
        // Clean any existing output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start output buffering
        ob_start();
        
        // Ensure we always return JSON, even on fatal errors
        if (!headers_sent()) {
        header('Content-Type: application/json');
        }
        
        // Set error handler to catch any fatal errors
        set_error_handler(function($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        
        try {
            $rawInput = file_get_contents('php://input');
            if (empty($rawInput)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No request data received']);
                ob_end_flush();
                exit;
            }
            
            $data = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON data: ' . json_last_error_msg()]);
                ob_end_flush();
                exit;
            }
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request data: ' . $e->getMessage()]);
            ob_end_flush();
            exit;
        }
        
        if (!isset($data['username']) || !isset($data['password'])) {
            ob_end_clean();
            if (!headers_sent()) {
                header('Content-Type: application/json');
            http_response_code(400);
            }
            echo json_encode(['success' => false, 'error' => 'Username and password are required']);
            exit;
        }

        try {
            // Test database connection first
            if (!$this->auth) {
                throw new \Exception('Authentication service not available');
            }
            
            // Trim username and password to remove whitespace
            $username = trim($data['username']);
            $password = trim($data['password']);
            
            $result = $this->auth->login($username, $password);
            
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Set token in session for server-side authentication
            $_SESSION['token'] = $result['token'];
            $_SESSION['user'] = [
                'id' => $result['user']['id'],
                'username' => $result['user']['username'],
                'email' => $result['user']['email'] ?? null,
                'full_name' => $result['user']['full_name'] ?? null,
                'role' => $result['user']['role'],
                'company_id' => $result['user']['company_id'] ?? null,
                'company_name' => $result['user']['company_name'] ?? null
            ];
            
            // Set initial last activity time for session timeout tracking
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time(); // Store login time for session duration calculation
            
            // Set JWT token in cookie for web page authentication
            // Cookie expires in 24 hours (same as JWT)
            $cookieExpire = time() + (24 * 60 * 60);
            setcookie('sellapp_token', $result['token'], $cookieExpire, '/', '', false, true); // HttpOnly for security
            setcookie('token', $result['token'], $cookieExpire, '/', '', false, true); // Alternative name for compatibility
            
            // Log audit event for login
            try {
                $auditService = new \App\Services\AuditService();
                $auditService->logEvent(
                    $result['user']['company_id'] ?? null,
                    $result['user']['id'],
                    'user.login',
                    'user',
                    $result['user']['id'],
                    [
                        'username' => $result['user']['username'],
                        'role' => $result['user']['role'],
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ],
                    $_SERVER['REMOTE_ADDR'] ?? null
                );
            } catch (\Exception $auditError) {
                // Don't fail login if audit logging fails
                error_log("Audit logging error (non-fatal): " . $auditError->getMessage());
            }
            
            // Log user activity for login
            try {
                $activityLog = new \App\Models\UserActivityLog();
                $activityLog->logLogin(
                    $result['user']['id'],
                    $result['user']['company_id'] ?? null,
                    $result['user']['role'],
                    $result['user']['username'],
                    $result['user']['full_name'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
            } catch (\Exception $activityError) {
                // Don't fail login if activity logging fails
                error_log("User activity logging error (non-fatal): " . $activityError->getMessage());
            }
            
            $response = json_encode([
                'success' => true,
                'message' => 'Login successful',
                'data' => $result,
                'redirect' => '/dashboard'  // Always redirect to unified dashboard
            ]);
            
            ob_end_clean();
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo $response;
            exit;
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log("Login error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Check if it's a database connection error
            $isDbError = strpos($e->getMessage(), 'Database connection') !== false || 
                        strpos($e->getMessage(), 'SQLSTATE') !== false;
            
            $errorResponse = [
                'success' => false,
                'error' => $e->getMessage()
            ];
            
            // Add debug info only in local environment
            if (defined('APP_ENV') && APP_ENV === 'local') {
                $errorResponse['debug'] = [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ];
            }
            
            ob_end_clean();
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code($isDbError ? 500 : 401);
            }
            echo json_encode($errorResponse);
            exit;
        } catch (\Error $e) {
            // Catch fatal errors
            error_log("Login fatal error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            ob_end_clean();
            if (!headers_sent()) {
                header('Content-Type: application/json');
            http_response_code(500);
            }
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ]);
            exit;
        } finally {
            // Restore error handler
            restore_error_handler();
        }
    }

    /**
     * Register endpoint
     * POST /api/auth/register
     */
    public function register() {
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required = ['username', 'email', 'password', 'full_name'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Field '$field' is required"]);
                return;
            }
        }

        try {
            $result = $this->auth->register($data);
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Logout endpoint
     * POST /api/auth/logout
     */
    public function logout() {
        // Clean any existing output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start output buffering
        ob_start();
        
        // Set JSON header immediately
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Log user activity for logout before clearing session
        try {
            $userId = $_SESSION['user']['id'] ?? null;
            $loginTime = $_SESSION['login_time'] ?? null;
            
            if ($userId) {
                $activityLog = new \App\Models\UserActivityLog();
                $activityLog->logLogout($userId, $loginTime);
            }
        } catch (\Exception $activityError) {
            // Don't fail logout if activity logging fails
            error_log("User activity logging error on logout (non-fatal): " . $activityError->getMessage());
        }
        
        // Clear all session data
        $_SESSION = array();
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        // Clear authentication cookies
        setcookie('sellapp_token', '', time() - 3600, '/', '', false, true);
        setcookie('token', '', time() - 3600, '/', '', false, true);
        
        // Clean output buffer and send response
        ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
        exit;
    }

    /**
     * Validate token endpoint (protected)
     * GET /api/auth/validate
     */
    public function validate() {
        header('Content-Type: application/json');
        try {
            $payload = \App\Middleware\AuthMiddleware::handle();
            echo json_encode([
                'success' => true,
                'valid' => true,
                'user' => [
                    'id' => $payload->sub,
                    'unique_id' => $payload->unique_id,
                    'username' => $payload->username,
                    'role' => $payload->role,
                    'company_id' => $payload->company_id ?? null,
                    'company_name' => $payload->company_name ?? '', // always present for UI
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'valid' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate localStorage token and set in session
     * POST /api/auth/validate-local-token
     */
    public function validateLocalToken() {
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['token']) || empty($data['token'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Token is required'
            ]);
            return;
        }

        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check if we're already validating to prevent multiple calls
            if (isset($_SESSION['validating_token']) && $_SESSION['validating_token'] === $data['token']) {
                // Check if we've been validating for more than 5 seconds (likely stuck in loop)
                if (isset($_SESSION['validating_token_time']) && (time() - $_SESSION['validating_token_time']) > 5) {
                    unset($_SESSION['validating_token']);
                    unset($_SESSION['validating_token_time']);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Token validation timeout'
                    ]);
                    return;
                }
                echo json_encode([
                    'success' => false,
                    'error' => 'Token validation already in progress'
                ]);
                return;
            }
            
            // Set validation flag with timestamp
            $_SESSION['validating_token'] = $data['token'];
            $_SESSION['validating_token_time'] = time();
            
            // Validate the token
            $payload = $this->auth->validateToken($data['token']);
            
            // Set token in session for future requests
            $_SESSION['token'] = $data['token'];
            $_SESSION['user'] = [
                'id' => $payload->sub,
                'username' => $payload->username,
                'role' => $payload->role,
                'company_id' => $payload->company_id,
                'company_name' => $payload->company_name ?? ''
            ];
            
            // Set initial last activity time for session timeout tracking
            $_SESSION['last_activity'] = time();
            
            // Clear validation flag
            unset($_SESSION['validating_token']);
            unset($_SESSION['validating_token_time']);
            
            // Debug logging
            error_log("validateLocalToken: Session ID - " . session_id());
            error_log("validateLocalToken: User set in session - " . json_encode($_SESSION['user']));
            error_log("validateLocalToken: Session save path - " . session_save_path());
            
            // Force session write to ensure it's persisted before response
            session_write_close();
            
            // Restart session for any subsequent operations
            session_start();
            
            // Verify session was properly saved
            $verifyUser = $_SESSION['user'] ?? null;
            error_log("validateLocalToken: Session verification after restart - " . ($verifyUser ? "SUCCESS" : "FAILED"));
            
            echo json_encode([
                'success' => true,
                'message' => 'Token validated and session set',
                'session_id' => session_id(), // Send session ID for debugging
                'user' => [
                    'id' => $payload->sub,
                    'username' => $payload->username,
                    'role' => $payload->role
                ]
            ]);
        } catch (\Exception $e) {
            // Clear validation flag on error
            unset($_SESSION['validating_token']);
            
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Re-authenticate with password when session expires
     * POST /api/auth/reauthenticate
     */
    public function reauthenticate() {
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if session is expired
        if (!isset($_SESSION['session_expired']) || !$_SESSION['session_expired']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Session is not expired'
            ]);
            return;
        }
        
        // Get password from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['password']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Password is required'
            ]);
            return;
        }
        
        // Get user from session
        $user = $_SESSION['user'] ?? null;
        if (!$user || !isset($user['username'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'User session not found'
            ]);
            return;
        }
        
        try {
            // Verify password
            $result = $this->auth->login($user['username'], $data['password']);
            
            // Update session with new token
            $_SESSION['token'] = $result['token'];
            $_SESSION['user'] = [
                'id' => $result['user']['id'],
                'username' => $result['user']['username'],
                'email' => $result['user']['email'] ?? null,
                'full_name' => $result['user']['full_name'] ?? null,
                'role' => $result['user']['role'],
                'company_id' => $result['user']['company_id'] ?? null,
                'company_name' => $result['user']['company_name'] ?? null
            ];
            
            // Clear expiration flags
            unset($_SESSION['session_expired']);
            unset($_SESSION['session_expired_at']);
            
            // Update last activity time
            $_SESSION['last_activity'] = time();
            
            // Update cookies
            $cookieExpire = time() + (24 * 60 * 60);
            setcookie('sellapp_token', $result['token'], $cookieExpire, '/', '', false, true);
            setcookie('token', $result['token'], $cookieExpire, '/', '', false, true);
            
            echo json_encode([
                'success' => true,
                'message' => 'Re-authentication successful'
            ]);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sync audit trail data on login (Phase 6.1 - Optional Enhancement)
     * Non-blocking background sync
     */
    private function syncAuditTrail($companyId, $userId) {
        try {
            // Generate recommendations (if enabled)
            $analyticsService = new AnalyticsService();
            $analyticsService->generateRecommendations($companyId, true);
            
            // Optionally trigger alert check
            // $alertService = new \App\Services\AlertService();
            // $alertService->checkAndTrigger($companyId);
            
            // Log sync event
            \App\Services\AuditService::log(
                $companyId,
                $userId,
                'audit_trail.synced',
                'audit_trail',
                null,
                ['triggered_by' => 'login']
            );
        } catch (\Exception $e) {
            // Silently fail - don't block login
            error_log("Audit trail sync error: " . $e->getMessage());
        }
    }
}

