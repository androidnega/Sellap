<?php
/**
 * Database Session Configuration
 * This file configures PHP to use database sessions instead of file system
 * Include this file before session_start() to enable database sessions
 */

// Only configure if session hasn't started
if (session_status() === PHP_SESSION_NONE) {
    // Set session handler to use database
    $sessionHandler = new \App\Services\DatabaseSessionHandler();
    session_set_save_handler($sessionHandler, true);
    
    // Set session configuration
    ini_set('session.gc_maxlifetime', defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800);
    
    // Detect HTTPS
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    $sessionPath = defined('BASE_URL_PATH') && !empty(BASE_URL_PATH) ? BASE_URL_PATH : '/';
    session_set_cookie_params([
        'lifetime' => defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800,
        'path' => $sessionPath,
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

