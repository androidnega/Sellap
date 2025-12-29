<?php

/**
 * Application Configuration
 * Global constants and settings for SellApp
 */

// Timezone Configuration
date_default_timezone_set('UTC');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application Constants
// Auto-detect APP_URL from server if not set in environment
$detectedAppUrl = null;
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $detectedAppUrl = "{$protocol}://{$host}";
}

define('APP_NAME', 'SellApp');
define('APP_URL', getenv('APP_URL') ?: ($detectedAppUrl ?: 'https://sellapp.store'));
define('APP_ENV', getenv('APP_ENV') ?: 'local');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_secret_key');

// Base URL Path - Auto-detect from request URI for live server compatibility
// For root domain (sellapp.store), use empty string
// For subdirectory installations, use the subdirectory path
$basePath = ''; // Default to root (empty string)

// Auto-detect base path from request URI
if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['SCRIPT_NAME'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    // Remove query string from request URI
    $requestUri = strtok($requestUri, '?');
    
    // Get the directory of the script (e.g., /sellapp/index.php -> /sellapp)
    $scriptDir = dirname($scriptName);
    
    // If script is in root directory (index.php in root), use empty base path
    if ($scriptDir === '/' || $scriptDir === '.') {
        $basePath = '';
    } else {
        // Script is in a subdirectory, use that as base path
        $basePath = rtrim($scriptDir, '/');
    }
    
    // Check if we're on a root domain by checking HTTP_HOST
    // If domain is sellapp.store (or similar root domain), force empty base path
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    if (preg_match('#^sellapp\.store$#', $httpHost) || 
        preg_match('#^www\.sellapp\.store$#', $httpHost)) {
        // Root domain - use empty base path
        $basePath = '';
    }
}

// No additional base path cleanup needed

// Normalize: empty string or '/' both mean root
if ($basePath === '/') {
    $basePath = '';
}

define('BASE_URL_PATH', $basePath);

// Network IP Configuration - for local network access
// Set this if you want to access from other computers on your network
// Example: '192.168.33.85'
define('NETWORK_IP', getenv('NETWORK_IP') ?: '192.168.33.85');

// File System Path Constants
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('VIEWS_PATH', BASE_PATH . '/resources/views');
define('ASSETS_PATH', BASE_PATH . '/assets');

// Session Configuration
// Session timeout: 30 minutes of inactivity (in seconds)
define('SESSION_TIMEOUT', 30 * 60); // 1800 seconds = 30 minutes

// Set custom session save path for cPanel/shared hosting compatibility
// This fixes the "Read-only file system" error on cPanel
if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = STORAGE_PATH . '/sessions';
    
    // Create session directory if it doesn't exist
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0755, true);
    }
    
    // Set session save path to writable directory
    // Only set if directory exists and is writable
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    } else {
        // Fallback: try to use a directory in the user's home directory
        // This is common for cPanel environments
        $homeDir = getenv('HOME');
        if ($homeDir && is_dir($homeDir)) {
            $fallbackDir = $homeDir . '/sellapp_sessions';
            if (!is_dir($fallbackDir)) {
                @mkdir($fallbackDir, 0755, true);
            }
            if (is_dir($fallbackDir) && is_writable($fallbackDir)) {
                session_save_path($fallbackDir);
            }
        }
    }
}

// Only set session configuration if session is not already active
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    
    // Detect if we're using HTTPS
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    // Set session cookie path to base path for proper cookie handling
    // If BASE_URL_PATH is empty, use '/' for root domain
    $sessionPath = defined('BASE_URL_PATH') && !empty(BASE_URL_PATH) ? BASE_URL_PATH : '/';
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => $sessionPath,
        'domain' => '',
        'secure' => $isSecure, // Auto-detect HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

