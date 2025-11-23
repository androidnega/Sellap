<?php

namespace App\Helpers;

/**
 * Helper to block admin users from accessing role-specific pages
 */
class AdminBlockHelper {
    
    /**
     * Check and block admin users from accessing the page
     * Returns true if access should be blocked, false if allowed
     */
    public static function shouldBlockAdmin($allowedRoles = []) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userData = $_SESSION['user'] ?? null;
        if (!$userData) {
            return false; // Not logged in - let auth middleware handle it
        }
        
        $userRole = strtolower(trim($userData['role'] ?? ''));
        $username = strtolower(trim($userData['username'] ?? ''));
        
        // Debug logging
        error_log("AdminBlockHelper::shouldBlockAdmin - User role: '" . $userRole . "'");
        error_log("AdminBlockHelper::shouldBlockAdmin - Allowed roles: " . json_encode($allowedRoles));
        error_log("AdminBlockHelper::shouldBlockAdmin - User data: " . json_encode($userData));
        
        // Explicitly block 'admin' role OR username (case-insensitive)
        if ($userRole === 'admin' || $username === 'admin' || $username === 'administrator') {
            error_log("AdminBlockHelper::shouldBlockAdmin - BLOCKING: User is admin (role or username match)");
            return true;
        }
        
        // If allowed roles are specified, check if user role is in the list
        if (!empty($allowedRoles)) {
            $allowedRoles = array_map('strtolower', $allowedRoles);
            if (!in_array($userRole, $allowedRoles)) {
                error_log("AdminBlockHelper::shouldBlockAdmin - BLOCKING: User role '" . $userRole . "' not in allowed list");
                return true;
            }
        }
        
        error_log("AdminBlockHelper::shouldBlockAdmin - ALLOWING: User role is allowed");
        return false;
    }
    
    /**
     * Show access denied page and exit
     */
    public static function showAccessDenied($message = null, $redirectUrl = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userData = $_SESSION['user'] ?? null;
        $userRole = $userData['role'] ?? 'unknown';
        
        $title = 'Access Denied';
        $errorMessage = $message ?? "You do not have permission to access this page. Your current role is: " . htmlspecialchars($userRole);
        
        ob_start();
        ?>
        <div class="max-w-2xl mx-auto">
            <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                <div class="flex items-center mb-4">
                    <svg class="w-8 h-8 text-red-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <h1 class="text-2xl font-bold text-red-900">Access Denied</h1>
                </div>
                <p class="text-red-800 mb-4"><?= htmlspecialchars($errorMessage) ?></p>
                <a href="<?= htmlspecialchars($redirectUrl ?? BASE_URL_PATH . '/dashboard') ?>" class="inline-block px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                    Return to Dashboard
                </a>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        
        $GLOBALS['content'] = $content;
        $GLOBALS['title'] = $title;
        if ($userData) {
            $GLOBALS['user_data'] = $userData;
        }
        
        require __DIR__ . '/../Views/simple_layout.php';
        exit;
    }
    
    /**
     * Check and block if admin - convenience method
     */
    public static function blockAdmin($allowedRoles = [], $message = null, $redirectUrl = null) {
        if (self::shouldBlockAdmin($allowedRoles)) {
            self::showAccessDenied($message, $redirectUrl);
        }
    }
}

