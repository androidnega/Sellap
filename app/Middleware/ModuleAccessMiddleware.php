<?php

namespace App\Middleware;

use App\Models\CompanyModule;

/**
 * Module Access Middleware
 * Prevents access to disabled modules for a company
 * 
 * System admins bypass module checks (they can access everything)
 * 
 * Usage:
 * - For API routes: ModuleAccessMiddleware::handle('swap');
 * - For Web routes: ModuleAccessMiddleware::handle('swap', $userPayload);
 */
class ModuleAccessMiddleware {
    
    /**
     * Check if user's company has the required module enabled
     * 
     * @param string $requiredModule Module key (e.g., 'swap', 'pos_sales', 'repairs')
     * @param object|null $userPayload Optional user payload from AuthMiddleware/WebAuthMiddleware
     *                                 If not provided, will try to get from session or request
     * @return void Exits with 403 if module is disabled
     */
    public static function handle($requiredModule, $userPayload = null) {
        // Get user payload if not provided
        if ($userPayload === null) {
            $userPayload = self::getUserPayload();
        }
        
        // If no user payload, user is not authenticated
        if (!$userPayload) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        // System admins bypass module checks (they have access to all modules)
        if (isset($userPayload->role) && $userPayload->role === 'system_admin') {
            return;
        }
        
        // Get company_id from payload
        $companyId = $userPayload->company_id ?? null;
        
        // If no company_id, user doesn't belong to a company (shouldn't happen for non-system_admin)
        if (!$companyId) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Company association required']);
            exit;
        }
        
        // Check if module is enabled for this company
        if (!CompanyModule::isEnabled($companyId, $requiredModule)) {
            // Determine response format based on request type
            $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
            
            if ($isApiRequest) {
                // API response
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Module Disabled for this company',
                    'module' => $requiredModule,
                    'message' => "The {$requiredModule} module is not enabled for your company. Please contact your administrator."
                ]);
            } else {
                // Web response - redirect to dashboard with error message
                $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
                $moduleName = ucfirst(str_replace('_', ' ', $requiredModule));
                
                // Store error in session for display
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['module_error'] = "The {$moduleName} module is not enabled for your company. Please contact your administrator.";
                
                // Redirect to dashboard
                header('Location: ' . $basePath . '/dashboard');
                exit;
            }
            exit;
        }
        
        // Module is enabled, allow access
        return;
    }
    
    /**
     * Get user payload from session or auth header
     * Helper method to support both web and API contexts
     * 
     * @return object|null User payload or null if not authenticated
     */
    private static function getUserPayload() {
        // Try to get from session first (web routes)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $sessionUser = $_SESSION['user'] ?? null;
        if ($sessionUser) {
            return (object) $sessionUser;
        }
        
        // Try to get from AuthMiddleware (API routes)
        // This will only work if called after AuthMiddleware
        try {
            // Check for Authorization header
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
                $auth = new \App\Services\AuthService();
                $payload = $auth->validateToken($token);
                return $payload;
            }
        } catch (\Exception $e) {
            // Token validation failed, return null
        }
        
        return null;
    }
    
    /**
     * Check multiple modules (all must be enabled)
     * 
     * @param array $requiredModules Array of module keys
     * @param object|null $userPayload Optional user payload
     * @return void Exits with 403 if any module is disabled
     */
    public static function handleMultiple($requiredModules, $userPayload = null) {
        foreach ($requiredModules as $module) {
            self::handle($module, $userPayload);
            // If we get here, module is enabled, continue to next
        }
    }
    
    /**
     * Check if at least one module is enabled (OR logic)
     * 
     * @param array $requiredModules Array of module keys
     * @param object|null $userPayload Optional user payload
     * @return void Exits with 403 if all modules are disabled
     */
    public static function handleAny($requiredModules, $userPayload = null) {
        // Get user payload if not provided
        if ($userPayload === null) {
            $userPayload = self::getUserPayload();
        }
        
        if (!$userPayload) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        // System admins bypass module checks
        if (isset($userPayload->role) && $userPayload->role === 'system_admin') {
            return;
        }
        
        $companyId = $userPayload->company_id ?? null;
        if (!$companyId) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Company association required']);
            exit;
        }
        
        // Check if at least one module is enabled
        foreach ($requiredModules as $module) {
            if (CompanyModule::isEnabled($companyId, $module)) {
                // At least one module is enabled, allow access
                return;
            }
        }
        
        // All modules are disabled
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'No Required Module Enabled',
            'modules' => $requiredModules,
            'message' => 'None of the required modules are enabled for your company.'
        ]);
        exit;
    }
}

