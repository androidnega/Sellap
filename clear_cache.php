<?php
/**
 * Clear PHP OpCache
 * Run this from browser: http://localhost/sellapp/clear_cache.php
 */

// Load config to get BASE_URL_PATH
if (file_exists(__DIR__ . '/config/app.php')) {
    require_once __DIR__ . '/config/app.php';
}

// Clear opcache if enabled
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<p style='color: green;'>✓ OpCache cleared successfully</p>";
} else {
    echo "<p style='color: orange;'>OpCache is not enabled</p>";
}

// Clear any other caches
if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo "<p style='color: green;'>✓ APC cache cleared successfully</p>";
}

echo "<hr>";
$basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
echo "<p><a href='{$basePath}/dashboard'>Go to Dashboard</a></p>";

