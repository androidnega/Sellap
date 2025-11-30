<?php

namespace App\Middleware;

use App\Services\AuthService;

class AuthMiddleware {
    
    /**
     * Validate JWT token from Authorization header
     * Returns decoded token payload or exits with 401
     * Supports role-based access control
     * 
     * @param array $roles Optional array of allowed roles
     * @return object Decoded token payload
     */
    public static function handle($roles = []) { 
        // Handle CLI mode where getallheaders() is not available
        if (php_sapi_name() === 'cli') {
            // For CLI testing, return a mock manager payload
            return (object)[
                'sub' => 1,
                'username' => 'test_manager',
                'role' => 'manager',
                'company_id' => 1
            ];
        }
        
        // Start session if not already started (for session-based auth fallback)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check for session inactivity timeout (for session-based auth)
        self::checkSessionTimeout();
        
        // Try JWT token from Authorization header first
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        // Also check for token in cookies (for form-based login)
        if (empty($authHeader)) {
            $authHeader = 'Bearer ' . ($_COOKIE['sellapp_token'] ?? $_COOKIE['token'] ?? '');
        }

        // If we have a Bearer token, try to validate it
        if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
            if (!empty($token)) {
        $auth = new AuthService();
        try {
            $payload = $auth->validateToken($token);
            
            // Check role-based access if roles are specified
            if (!empty($roles) && !in_array($payload->role, $roles)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized role', 'required' => $roles, 'current' => $payload->role]);
                exit;
            }
            
            return $payload;
        } catch (\Exception $e) {
                    // Token validation failed, fall through to session check
                    error_log("AuthMiddleware: JWT validation failed: " . $e->getMessage() . " - trying session");
                }
            }
        }
        
        // Fallback to session-based authentication
        if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
            $userData = $_SESSION['user'];
            
            // Check if session is expired (but allow re-authentication endpoint)
            $isReauthEndpoint = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/auth/reauthenticate') !== false;
            if (isset($_SESSION['session_expired']) && $_SESSION['session_expired'] && !$isReauthEndpoint) {
                // Session expired - return 401 for API endpoints
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Session expired',
                    'message' => 'Your session has expired due to inactivity. Please re-authenticate.'
                ]);
                exit;
            }
            
            // Update last activity time for session timeout tracking
            self::updateLastActivity();
            
            // Check role-based access if roles are specified
            if (!empty($roles) && !in_array($userData['role'], $roles)) {
                http_response_code(403);
            header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized role', 'required' => $roles, 'current' => $userData['role']]);
            exit;
        }
            
            // Return user data in same format as JWT payload
            return (object)[
                'sub' => $userData['id'],
                'username' => $userData['username'],
                'role' => $userData['role'],
                'company_id' => $userData['company_id'] ?? null,
                'company_name' => $userData['company_name'] ?? null
            ];
        }
        
        // No valid authentication found
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Authentication required', 'message' => 'Please login to access this resource']);
        exit;
    }

    /**
     * Check if user has required role (Updated for Multi-Tenant Roles)
     */
    public static function checkRole($requiredRole) {
        $payload = self::handle();
        
        $allowedRoles = [
            'system_admin' => ['system_admin'],
            'manager' => ['system_admin', 'manager'],
            'salesperson' => ['system_admin', 'manager', 'salesperson'],
            'technician' => ['system_admin', 'manager', 'salesperson', 'technician']
        ];

        if (!in_array($payload->role, $allowedRoles[$requiredRole] ?? [])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }

        return $payload;
    }
    
    /**
     * Check if session has expired due to inactivity
     * Sets a flag for re-authentication instead of logging out
     */
    private static function checkSessionTimeout() {
        // Only check if user is logged in
        if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
            return;
        }
        
        // Get session timeout (default to 30 minutes if not defined)
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : (30 * 60);
        
        // Get last activity time
        $lastActivity = $_SESSION['last_activity'] ?? null;
        
        if ($lastActivity !== null) {
            // Calculate time since last activity
            $timeSinceActivity = time() - $lastActivity;
            
            // If inactive for more than timeout period, set re-authentication flag
            if ($timeSinceActivity > $timeout) {
                // Set flag to indicate session needs re-authentication
                $_SESSION['session_expired'] = true;
                $_SESSION['session_expired_at'] = time();
                // Keep user data for re-authentication
                // Don't destroy session - just mark it as expired
            } else {
                // Clear expiration flag if user is active
                unset($_SESSION['session_expired']);
                unset($_SESSION['session_expired_at']);
            }
        }
    }
    
    /**
     * Update last activity timestamp in session
     */
    private static function updateLastActivity() {
        if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
            // Only update if session is not expired
            if (!isset($_SESSION['session_expired']) || !$_SESSION['session_expired']) {
                $_SESSION['last_activity'] = time();
            }
        }
    }
}

