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
        $returnUrl = $redirectUrl ?? (defined('BASE_URL_PATH') ? rtrim(BASE_URL_PATH, '/') . '/dashboard' : '/dashboard');
        
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(403);
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: radial-gradient(circle at top, #0f172a 0%, #020617 55%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 80px rgba(2, 6, 23, 0.8);
            text-align: center;
        }
        .icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 1.5rem;
            border-radius: 20px;
            background: rgba(248, 113, 113, 0.15);
            color: #f87171;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        h1 {
            margin: 0 0 1rem;
            font-size: 2rem;
            font-weight: 700;
        }
        p {
            margin: 0 0 2rem;
            color: rgba(248, 250, 252, 0.8);
            line-height: 1.6;
        }
        a.button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 1.75rem;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            letter-spacing: 0.02em;
            box-shadow: 0 15px 35px rgba(37, 99, 235, 0.35);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        a.button:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 45px rgba(37, 99, 235, 0.45);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">!</div>
        <h1>' . htmlspecialchars($title) . '</h1>
        <p>' . htmlspecialchars($errorMessage) . '</p>
        <a class="button" href="' . htmlspecialchars($returnUrl) . '">Return to Dashboard</a>
    </div>
</body>
</html>';
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

