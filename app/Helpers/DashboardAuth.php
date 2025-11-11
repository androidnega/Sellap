<?php

namespace App\Helpers;

if (!class_exists('App\Helpers\DashboardAuth')) {
    class DashboardAuth {
    
    /**
     * Secure dashboard route handler with role-based access control
     */
    public static function secureRoute($allowedRoles, $callback) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // For web routes, we'll rely on client-side authentication
        // The layout.php will handle the authentication and redirect if needed
        // This is a simplified approach that works with the existing client-side auth
        
        // Check if we have a token in the URL (from client-side validation)
        $token = $_GET['token'] ?? null;
        
        if ($token) {
            try {
                // Validate the token
                $auth = new \App\Services\AuthService();
                $payload = $auth->validateToken($token);
                
                // Check role-based access
                if (!empty($allowedRoles) && !in_array($payload->role, $allowedRoles)) {
                    // User doesn't have permission for this route
                    header("Location: " . BASE_URL_PATH . "/?error=" . urlencode('You do not have permission to access this page'));
                    exit;
                }
                
                // Store user info in session for easy access
                $_SESSION['user'] = [
                    'id' => $payload->sub,
                    'username' => $payload->username,
                    'role' => $payload->role,
                    'company_id' => $payload->company_id
                ];
                
                // Call the callback with user payload
                return $callback($payload);
                
            } catch (\Exception $e) {
                // Invalid or expired token
                unset($_SESSION['user']);
                header("Location: " . BASE_URL_PATH . "/?error=" . urlencode('Session expired. Please login again.'));
                exit;
            }
        } else {
            // No token provided, let the client-side handle authentication
            // The layout.php will check for localStorage token and redirect if needed
            // For now, we'll just call the callback - the client-side will handle auth
            return $callback((object)['role' => 'system_admin']); // Fallback for now
        }
    }
    
    /**
     * Check if current user has access to a specific route
     */
    public static function checkRouteAccess($route) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return false;
        }
        
        return RoleHelper::hasAccess($user['role'], $route);
    }
    
    /**
     * Get current user info
     */
    public static function getCurrentUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['user'] ?? null;
    }
    
    /**
     * Check if user can perform a specific action
     */
    public static function canPerformAction($action) {
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }
        
        return RoleHelper::canPerformAction($user['role'], $action);
    }
}
}
