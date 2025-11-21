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
define('APP_NAME', 'SellApp');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
define('APP_ENV', getenv('APP_ENV') ?: 'local');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_secret_key');

// Base URL Path - Auto-detect from request URI for live server compatibility
// This will work for both local development and live server
$basePath = '/sellapp'; // Default fallback

// Auto-detect base path from request URI
if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['SCRIPT_NAME'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    // Remove query string from request URI
    $requestUri = strtok($requestUri, '?');
    
    // Get the directory of the script (e.g., /sellapp/index.php -> /sellapp)
    $scriptDir = dirname($scriptName);
    
    // If script is in a subdirectory, use that as base path
    if ($scriptDir !== '/' && $scriptDir !== '.') {
        $basePath = rtrim($scriptDir, '/');
    } else {
        // Try to detect from request URI
        // If request URI starts with a path, extract it
        if (preg_match('#^/([^/]+)/#', $requestUri, $matches)) {
            $basePath = '/' . $matches[1];
        }
    }
}

// Final safety check - ensure kabz_events is never in base path
$basePath = preg_replace('#/kabz_events(/|$)#', '/', $basePath);
$basePath = str_replace('kabz_events', '', $basePath);
if (empty($basePath) || $basePath === '/') {
    $basePath = '/sellapp';
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
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);

// Detect if we're using HTTPS
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

// Set session cookie path to base path for proper cookie handling
$sessionPath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '/';
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path' => $sessionPath,
    'domain' => '',
    'secure' => $isSecure, // Auto-detect HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

