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
            // Log the error and throw a more user-friendly message
            error_log("AuthController constructor error: " . $e->getMessage());
            throw new \Exception('Authentication service initialization failed');
        }
    }

    /**
     * Login endpoint
     * POST /api/auth/login
     */
    public function login() {
        // Ensure we always return JSON, even on fatal errors
        header('Content-Type: application/json');
        
        // Set error handler to catch any fatal errors
        set_error_handler(function($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request data']);
            return;
        }
        
        if (!isset($data['username']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Username and password are required']);
            return;
        }

        try {
            // Test database connection first
            if (!$this->auth) {
                throw new \Exception('Authentication service not available');
            }
            
            $result = $this->auth->login($data['username'], $data['password']);
            
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
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'data' => $result,
                'redirect' => '/dashboard'  // Always redirect to unified dashboard
            ]);
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        } catch (\Error $e) {
            // Catch fatal errors
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage()
            ]);
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
                'company_id' => $payload->company_id
            ];
            
            // Set initial last activity time for session timeout tracking
            $_SESSION['last_activity'] = time();
            
            // Clear validation flag
            unset($_SESSION['validating_token']);
            unset($_SESSION['validating_token_time']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Token validated and session set',
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

