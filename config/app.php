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

// Base URL Path - Set for sellapp subdirectory
// Force to /sellapp (not kabz_events) for this project
// This project is in /sellapp, not /kabz_events
// Completely ignore any kabz_events references
$basePath = '/sellapp';
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
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path' => '/',
    'domain' => '',
    'secure' => false, // Set to true if using HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

