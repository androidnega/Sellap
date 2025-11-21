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
}

