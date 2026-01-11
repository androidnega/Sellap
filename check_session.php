<?php
/**
 * Session Diagnostic Tool
 * Check if session is working properly
 */
session_start();

header('Content-Type: application/json');

$response = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_name' => session_name(),
    'session_save_path' => session_save_path(),
    'session_data' => $_SESSION ?? [],
    'has_user' => isset($_SESSION['user']),
    'user_id' => $_SESSION['user']['id'] ?? null,
    'user_role' => $_SESSION['user']['role'] ?? null,
    'company_id' => $_SESSION['user']['company_id'] ?? null,
    'cookies' => $_COOKIE,
    'server_time' => date('Y-m-d H:i:s'),
    'session_cookie_params' => session_get_cookie_params()
];

echo json_encode($response, JSON_PRETTY_PRINT);

