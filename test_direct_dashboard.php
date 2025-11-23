<?php
/**
 * Direct test - bypass all routing and directly call the dashboard
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is in session
$userData = $_SESSION['user'] ?? null;

if (!$userData) {
    die('Error: No user in session. Please <a href="/">login first</a>.');
}

error_log("=== DIRECT DASHBOARD TEST ===");
error_log("User: " . json_encode($userData));
error_log("Session ID: " . session_id());
error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

// Now directly instantiate and call the dashboard controller
$controller = new \App\Controllers\DashboardController();

// Output buffer to catch any output
ob_start();

try {
    $controller->index();
    $output = ob_get_clean();
    
    // Check if output has meta refresh or JavaScript redirects
    if (preg_match('/meta.*refresh/i', $output)) {
        error_log("FOUND META REFRESH IN OUTPUT!");
        echo "<h1 style='color:red;'>ERROR: Meta refresh found in output!</h1>";
        echo "<pre>" . htmlspecialchars(substr($output, 0, 2000)) . "</pre>";
    } elseif (preg_match('/window\.location|location\.href|location\.reload|location\.replace/i', $output, $matches)) {
        error_log("FOUND JAVASCRIPT REDIRECT IN OUTPUT: " . $matches[0]);
        echo "<h1 style='color:red;'>ERROR: JavaScript redirect found!</h1>";
        echo "<pre>Found: " . htmlspecialchars($matches[0]) . "</pre>";
        echo "<hr>";
        echo "<h3>First 3000 characters of output:</h3>";
        echo "<pre>" . htmlspecialchars(substr($output, 0, 3000)) . "</pre>";
    } else {
        // No obvious redirects, output the page
        echo $output;
    }
} catch (\Exception $e) {
    ob_end_clean();
    echo "<h1 style='color:red;'>Exception: " . htmlspecialchars($e->getMessage()) . "</h1>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    error_log("Exception in dashboard: " . $e->getMessage());
}
?>

