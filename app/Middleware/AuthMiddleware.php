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
        
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (strpos($authHeader, 'Bearer ') !== 0) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing or invalid authorization header']);
            exit;
        }

        $token = substr($authHeader, 7);
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
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid or expired token', 'message' => $e->getMessage()]);
            exit;
        }
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

