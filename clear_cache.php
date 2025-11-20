<?php
/**
 * Clear PHP OpCache
 * Run this from browser: http://localhost/sellapp/clear_cache.php
 */

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
echo "<p><a href='/sellapp/dashboard'>Go to Dashboard</a></p>";

