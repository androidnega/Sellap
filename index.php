<?php

/**
 * SellApp - Main Entry Point
 * cPanel Shared Hosting Compatible
 */

// Serve static files directly before routing
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Remove query string
$requestPath = strtok($requestPath, '?');

// Normalize the path
$requestPath = trim($requestPath, '/');

// Check if this is a static asset request by extension or path
$staticExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'pdf', 'zip', 'mp4', 'mp3'];
$extension = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
$isStaticFile = in_array($extension, $staticExtensions) || strpos($requestPath, 'assets/') !== false;

if ($isStaticFile) {
    // Extract the actual file path from the request
    // Request might be: "sellapp/assets/css/styles.css" or "assets/css/styles.css"
    $cleanPath = $requestPath;
    
    // Remove "sellapp/" prefix if present
    $cleanPath = preg_replace('#^sellapp/#', '', $cleanPath);
    $cleanPath = preg_replace('#^/sellapp/#', '', $cleanPath);
    
    // Build the file path
    $filePath = __DIR__ . '/' . $cleanPath;
    
    // If file doesn't exist, try extracting just the assets part
    if (!file_exists($filePath) || !is_file($filePath)) {
        // Extract assets path: "sellapp/assets/css/styles.css" -> "assets/css/styles.css"
        if (preg_match('#(assets/.*)$#', $requestPath, $matches)) {
            $filePath = __DIR__ . '/' . $matches[1];
        }
    }
    
    // Check if file exists and is readable
    if (file_exists($filePath) && is_file($filePath) && is_readable($filePath)) {
        // Set appropriate MIME type
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg'
        ];
        
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        
        // Cache control for static assets
        header('Cache-Control: public, max-age=31536000');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        readfile($filePath);
        exit;
    } else {
        // File doesn't exist - return 404
        http_response_code(404);
        header('Content-Type: text/plain');
        if (defined('APP_ENV') && APP_ENV === 'local') {
            echo "File not found. Requested: {$requestPath}\n";
            echo "Tried path: {$filePath}\n";
        } else {
            echo 'File not found';
        }
        exit;
    }
}

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

