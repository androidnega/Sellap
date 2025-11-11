<?php

namespace App\Middleware;

use App\Services\AuthService;

require_once __DIR__ . '/../../config/database.php';

/**
 * Reset Permission Middleware (PHASE F)
 * Enforces strict permissions for reset operations
 * - Only system_admin can run system reset
 * - Only system_admin can run company reset (initially)
 * - Optional 2FA requirement
 */
class ResetPermissionMiddleware {
    
    /**
     * Check if user can perform system reset
     * Only system_admin allowed
     * 
     * @param bool $requireTwoFactor Optional 2FA requirement (future enhancement)
     * @return object User payload
     */
    public static function requireSystemResetPermission($requireTwoFactor = false) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session-based authentication first (for web routes)
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'system_admin') {
            $payload = (object) $_SESSION['user'];
        } else {
            // Fall back to JWT authentication (for API routes)
            try {
                $payload = AuthMiddleware::handle(['system_admin']);
            } catch (\Exception $e) {
                // If JWT fails but session exists, use session
                if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'system_admin') {
                    $payload = (object) $_SESSION['user'];
                } else {
                    // Neither session nor JWT valid, throw error
                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid or expired token',
                        'message' => 'Invalid or expired token: ' . $e->getMessage()
                    ]);
                    exit;
                }
            }
        }
        
        // Future: Add 2FA check here
        // if ($requireTwoFactor && !self::hasTwoFactorVerified($payload)) {
        //     http_response_code(403);
        //     header('Content-Type: application/json');
        //     echo json_encode(['error' => 'Two-factor authentication required']);
        //     exit;
        // }
        
        return $payload;
    }
    
    /**
     * Check if user can perform company reset
     * Only system_admin allowed initially (can be relaxed later)
     * 
     * @param int|null $companyId Company ID (for future manager check)
     * @param bool $requireTwoFactor Optional 2FA requirement
     * @return object User payload
     */
    public static function requireCompanyResetPermission($companyId = null, $requireTwoFactor = false) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session-based authentication first (for web routes)
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'system_admin') {
            $payload = (object) $_SESSION['user'];
        } else {
            // For now, only system_admin allowed
            // Future: Allow manager if $companyId matches their company
            try {
                $payload = AuthMiddleware::handle(['system_admin']);
            } catch (\Exception $e) {
                // If JWT fails but session exists, use session
                if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'system_admin') {
                    $payload = (object) $_SESSION['user'];
                } else {
                    // Neither session nor JWT valid, throw error
                    http_response_code(401);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid or expired token',
                        'message' => 'Invalid or expired token: ' . $e->getMessage()
                    ]);
                    exit;
                }
            }
        }
        
        // Future implementation:
        // if ($payload->role === 'manager') {
        //     if ((int)$payload->company_id !== (int)$companyId) {
        //         http_response_code(403);
        //         header('Content-Type: application/json');
        //         echo json_encode(['error' => 'Unauthorized: Cannot reset other companies']);
        //         exit;
        //     }
        // }
        
        // Future: Add 2FA check here
        // if ($requireTwoFactor && !self::hasTwoFactorVerified($payload)) {
        //     http_response_code(403);
        //     header('Content-Type: application/json');
        //     echo json_encode(['error' => 'Two-factor authentication required']);
        //     exit;
        // }
        
        return $payload;
    }
    
    /**
     * Require admin action permission (for viewing status/logs)
     * Only system_admin can view all reset actions
     * 
     * @return object User payload
     */
    public static function requireAdminActionPermission() {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session-based authentication first (for web routes)
        // Make sure session is properly resumed by using session_id()
        if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'system_admin') {
            return (object) $_SESSION['user'];
        }
        
        // Also check if there's a token in session that we can validate
        if (isset($_SESSION['token'])) {
            try {
                $auth = new \App\Services\AuthService();
                $payload = $auth->validateToken($_SESSION['token']);
                if ($payload && $payload->role === 'system_admin') {
                    // Update session user data from validated token
                    $_SESSION['user'] = [
                        'id' => $payload->sub,
                        'username' => $payload->username,
                        'role' => $payload->role,
                        'company_id' => $payload->company_id ?? null
                    ];
                    return $payload;
                }
            } catch (\Exception $e) {
                // Token validation failed, continue to JWT check
            }
        }
        
        // Fall back to JWT authentication (for API routes)
        try {
            $payload = AuthMiddleware::handle(['system_admin']);
            // If JWT is valid, also store in session for future requests
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['user'] = [
                    'id' => $payload->sub,
                    'username' => $payload->username,
                    'role' => $payload->role,
                    'company_id' => $payload->company_id ?? null
                ];
            }
            return $payload;
        } catch (\Exception $e) {
            // If JWT fails but session exists, use session
            if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'system_admin') {
                return (object) $_SESSION['user'];
            }
            
            // Neither session nor JWT valid, throw error
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Invalid or expired token',
                'message' => 'Invalid or expired token: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * Check if user has verified 2FA (placeholder for future implementation)
     * 
     * @param object $payload User payload
     * @return bool
     */
    private static function hasTwoFactorVerified($payload) {
        // TODO: Implement 2FA verification check
        // Check if user has verified 2FA in current session
        return true; // Placeholder - always allow for now
    }
}

