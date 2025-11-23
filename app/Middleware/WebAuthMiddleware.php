<?php

namespace App\Middleware;

use App\Services\AuthService;

class WebAuthMiddleware {
    
    /**
     * Handle web authentication for dashboard routes
     * Checks for token in session or localStorage via JavaScript
     * 
     * @param array $allowedRoles Optional array of allowed roles
     * @return object|null Decoded token payload or null if redirected
     */
    public static function handle($allowedRoles = []) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check for session inactivity timeout
        self::checkSessionTimeout();
        
        // Check for existing session-based user first
        $userData = $_SESSION['user'] ?? null;
        
        // Debug logging
        error_log("WebAuthMiddleware: Session ID - " . session_id());
        error_log("WebAuthMiddleware: User data - " . ($userData ? json_encode($userData) : "NULL"));
        error_log("WebAuthMiddleware: Request URI - " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        
        if ($userData && !empty($userData['role'])) {
            // User is already logged in via session
            // Update last activity time
            self::updateLastActivity();
            
            // Clear any auth attempt flags since we're authenticated
            unset($_SESSION['_auth_attempt']);
            unset($_SESSION['_auth_retry_count']);
            
            // Don't redirect if _auth parameter exists - let JavaScript clean it up
            // Server-side redirects were causing refresh loops
            if (isset($_GET['_auth'])) {
                error_log("WebAuthMiddleware: _auth parameter present but user authenticated - allowing page load");
            }
            
            if (!empty($allowedRoles) && !in_array($userData['role'], $allowedRoles)) {
                self::redirectToLogin('You do not have permission to access this page');
            }
            
            error_log("WebAuthMiddleware: User authenticated successfully - Role: " . $userData['role']);
            return (object) $userData;
        }
        
        // Check for token in session
        $token = $_SESSION['token'] ?? null;
        
        // If no token in session, redirect to login
        // Don't try to validate from localStorage - that causes loops
        if (!$token) {
            error_log("WebAuthMiddleware: No token in session, redirecting to login");
            
            // Clear any auth flags
            unset($_SESSION['_auth_attempt']);
            unset($_SESSION['_auth_retry_count']);
            
            // Redirect to login page
            $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            $currentPath = $_SERVER['REQUEST_URI'] ?? '/dashboard';
            $redirectParam = 'redirect=' . urlencode($currentPath);
            
            header('Location: ' . $basePath . '/?' . $redirectParam);
            exit;
        }
        
        try {
            // Validate the token
            $auth = new AuthService();
            $payload = $auth->validateToken($token);
            
            // Check role-based access if roles are specified
            if (!empty($allowedRoles) && !in_array($payload->role, $allowedRoles)) {
                self::redirectToLogin('You do not have permission to access this page');
            }
            
            // Store user info in session for easy access
            $_SESSION['user'] = [
                'id' => $payload->sub,
                'username' => $payload->username,
                'role' => $payload->role,
                'company_id' => $payload->company_id,
                'company_name' => $payload->company_name ?? ''
            ];
            
            // Clear any auth attempt flags since we're now authenticated
            unset($_SESSION['_auth_attempt']);
            unset($_SESSION['_auth_retry_count']);
            
            // Update last activity time after successful authentication
            self::updateLastActivity();
            
            return $payload;
            
        } catch (\Exception $e) {
            // Invalid or expired token
            unset($_SESSION['token']);
            unset($_SESSION['user']);
            self::redirectToLogin('Session expired. Please login again.');
        }
    }
    
    /**
     * Redirect to login page with JavaScript token validation
     */
    private static function redirectToLogin($error = null) {
        $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '/dashboard';
        
        // Get the path without base path for redirect
        $basePathClean = rtrim($basePath, '/');
        $currentPath = str_replace($basePathClean, '', $currentUrl);
        $currentPath = explode('?', $currentPath)[0]; // Remove query params
        $currentPath = explode('#', $currentPath)[0]; // Remove hash
        
        // Build redirect parameter
        $redirectParam = 'redirect=' . urlencode($currentPath);
        $errorParam = $error ? '&error=' . urlencode($error) : '';
        $queryParams = $redirectParam . $errorParam;
        
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Required - SellApp</title>
    <script>
        // Base path for application URLs
        window.APP_BASE_PATH = "{{BASE_PATH}}";
        const BASE = window.APP_BASE_PATH || "";
        
        // Get current URL for redirect after auth
        const currentUrl = "{{CURRENT_PATH}}";
        
        // Check if user has token in localStorage
        (function() {
            const token = localStorage.getItem("token") || localStorage.getItem("sellapp_token");
            
            if (token) {
                // Validate token and set in session
                fetch(BASE + "/api/auth/validate-local-token", {
                    method: "POST",
                    headers: {"Content-Type": "application/json"},
                    body: JSON.stringify({token: token})
                })
                .then(response => {
                    // Check if response is OK (200-299)
                    if (!response.ok) {
                        // Token validation failed (401, 400, etc.)
                        throw new Error("Token validation failed");
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Token is valid, reload page to continue
                        // Use a longer delay to ensure session cookie is fully saved
                        setTimeout(function() {
                            // Get clean URL without query params
                            const fullUrl = window.location.href.split("?")[0].split("#")[0];
                            // Remove _auth parameter if present to prevent loops
                            const urlParams = new URLSearchParams(window.location.search);
                            urlParams.delete("_auth");
                            const newParams = urlParams.toString();
                            let cleanUrl = fullUrl + (newParams ? "?" + newParams : "");
                            
                            // Just redirect to clean URL without _auth parameter
                            // The server will handle the session properly
                            window.location.replace(cleanUrl);
                        }, 1000);
                    } else {
                        // Token is invalid or expired, clear and redirect to login
                        localStorage.removeItem("token");
                        localStorage.removeItem("sellapp_token");
                        localStorage.removeItem("sellapp_user");
                        window.location.href = BASE + "/?error=" + encodeURIComponent("Your session has expired. Please login again.");
                    }
                })
                .catch(function(error) {
                    console.error("Token validation error:", error);
                    // Error validating token, redirect to login with current URL as redirect param
                    localStorage.removeItem("token");
                    localStorage.removeItem("sellapp_token");
                    window.location.href = BASE + "/?' . addslashes($queryParams) . '";
                });
            } else {
                // No token found, redirect to login with current URL as redirect param
                window.location.href = BASE + "/?' . addslashes($queryParams) . '";
            }
        })();
    </script>
</head>
<body>
    <div style="display: flex; justify-content: center; align-items: center; height: 100vh; font-family: Arial, sans-serif;">
        <div style="text-align: center;">
            <div style="margin-bottom: 20px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #3B82F6;"></i>
            </div>
            <p style="color: #6B7280;">Validating authentication...</p>
        </div>
    </div>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</body>
</html>
HTML;
        // Replace PHP variables in the heredoc
        $html = str_replace('{{BASE_PATH}}', addslashes($basePath), $html);
        $html = str_replace('{{CURRENT_PATH}}', addslashes($currentPath), $html);
        $html = str_replace('{{QUERY_PARAMS}}', addslashes($queryParams), $html);
        echo $html;
        exit;
    }
    
    /**
     * Get current user from session
     */
    public static function getCurrentUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['user'] ?? null;
    }
    
    /**
     * Check if user has required role
     */
    public static function hasRole($requiredRole) {
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }
        
        $roleHierarchy = [
            'system_admin' => ['system_admin'],
            'admin' => ['system_admin', 'admin'],
            'manager' => ['system_admin', 'admin', 'manager'],
            'salesperson' => ['system_admin', 'admin', 'manager', 'salesperson'],
            'technician' => ['system_admin', 'admin', 'manager', 'salesperson', 'technician']
        ];
        
        return in_array($user['role'], $roleHierarchy[$requiredRole] ?? []);
    }
    
    /**
     * Check if user can access a specific route
     */
    public static function canAccess($route) {
        $user = self::getCurrentUser();
        if (!$user) {
            return false;
        }
        
        $routePermissions = [
            'dashboard' => ['system_admin', 'admin', 'manager', 'salesperson', 'technician'],
            'companies' => ['system_admin'],
            'users' => ['system_admin'],
            'staff' => ['system_admin', 'admin', 'manager'],
            'categories' => ['system_admin', 'admin', 'manager'],
            'brands' => ['system_admin', 'admin', 'manager'],
            'subcategories' => ['system_admin', 'admin', 'manager'],
            'inventory' => ['system_admin', 'admin', 'manager', 'salesperson', 'technician'],
            'products' => ['system_admin', 'admin', 'manager', 'salesperson', 'technician'],
            'customers' => ['system_admin', 'admin', 'manager', 'salesperson'],
            'repairs' => ['system_admin', 'admin', 'manager', 'technician'],
            'swaps' => ['system_admin', 'admin', 'manager', 'salesperson'],
            'pos' => ['system_admin', 'admin', 'salesperson'],
            'reports' => ['system_admin', 'admin', 'manager']
        ];
        
        return in_array($user['role'], $routePermissions[$route] ?? []);
    }
    
    /**
     * Check if session has expired due to inactivity
     * Logs out user if inactive for more than 30 minutes
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
            
            // If inactive for more than timeout period, log out user
            if ($timeSinceActivity > $timeout) {
                // Clear session data
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
                
                // Redirect to login with timeout message
                self::redirectToLogin('Your session has expired due to inactivity. Please login again.');
            }
        }
    }
    
    /**
     * Update last activity timestamp in session
     */
    private static function updateLastActivity() {
        $_SESSION['last_activity'] = time();
    }
}