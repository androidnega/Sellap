<?php

/**
 * SellApp - Main Entry Point
 * cPanel Shared Hosting Compatible
 */

// Load Composer autoloader (if available)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Manual autoloader for App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load environment variables from .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Load application configuration
require_once __DIR__ . '/config/app.php';

// Load database configuration
require_once __DIR__ . '/config/database.php';

// Initialize Router
use App\Core\Router;

$router = new Router();

// Load routes
require_once __DIR__ . '/routes/web.php';

// Dispatch request
try {
    $router->dispatch();
} catch (Exception $e) {
    // Handle database connection errors gracefully
    if (strpos($e->getMessage(), 'Database connection failed') !== false) {
        // If this is an API request, return JSON error
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed. Please check your MySQL service.'
            ]);
            exit;
        } else {
            // For web requests, show a user-friendly error page
            http_response_code(500);
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Error</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .error { color: #d32f2f; background: #ffebee; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Database Connection Error</h1>
    <div class="error">
        <p>Unable to connect to the database. Please check:</p>
        <ul style="text-align: left; display: inline-block;">
            <li>MySQL service is running in XAMPP</li>
            <li>Database credentials are correct</li>
            <li>Port configuration is correct</li>
        </ul>
        <p><a href="setup_db.php">Run Database Setup</a></p>
    </div>
</body>
</html>';
            exit;
        }
    } else {
        // Re-throw other exceptions
        throw $e;
    }
}

