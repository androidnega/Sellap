<?php

/**
 * SellApp - Main Entry Point
 * cPanel Shared Hosting Compatible
 */

// Register fatal error handler early to catch fatal errors that cause 503
// Note: CloudinaryStorage will be loaded after database config
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_CORE_WARNING])) {
        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Log the fatal error - use error_log directly to avoid any class loading issues
        // Cloudinary logging will be handled by the application after it's fully loaded
        // DO NOT use CloudinaryStorage here as Database class may not be loaded yet
        error_log("Fatal error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        // Determine if this is an API request
        $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
        
        if (!headers_sent()) {
            // Use 503 for fatal errors that prevent service
            http_response_code(503);
            
            if ($isApiRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Service Unavailable',
                    'message' => 'The server encountered a fatal error and is temporarily unable to service your request.',
                    'type' => 'fatal_error'
                ]);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html>
<html>
<head>
    <title>Service Unavailable</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; }
        p { color: #666; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="error">
        <h1>Service Unavailable</h1>
        <p>The server is temporarily unable to service your request due to a technical error.</p>
        <p>Please try again later. If the problem persists, please contact the administrator.</p>
        <p style="margin-top: 20px; font-size: 12px; color: #999;">
            Error details have been logged for the administrator.
        </p>
    </div>
</body>
</html>';
            }
        }
        exit;
    }
});

// Set error handler for non-fatal errors
set_error_handler(function($severity, $message, $file, $line) {
    // Only handle errors that are not suppressed
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    // Log the error to Cloudinary (not file system)
    try {
        if (class_exists('CloudinaryStorage')) {
            CloudinaryStorage::logError("PHP Error [$severity]: $message in $file on line $line");
        } else {
            error_log("PHP Error [$severity]: $message in $file on line $line");
        }
    } catch (\Exception $e) {
        // Fallback to PHP error_log if Cloudinary fails
        error_log("PHP Error [$severity]: $message in $file on line $line");
    }
    
    // For fatal errors, let the shutdown function handle it
    if (in_array($severity, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        return false; // Let PHP handle fatal errors normally
    }
    
    return false; // Let PHP handle the error normally
});

// Serve static files directly before routing
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Remove query string
$requestPath = strtok($requestPath, '?');

// Check if this is a static asset request by extension or path
$staticExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'pdf', 'zip', 'mp4', 'mp3'];
$extension = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
$isStaticFile = in_array($extension, $staticExtensions) || strpos($requestPath, 'assets/') !== false;

if ($isStaticFile) {
    // Extract the actual file path from the request
    // Request might be: "/sellapp/assets/css/styles.css" or "/assets/css/styles.css"
    $cleanPath = $requestPath;
    
    // Remove leading slash
    $cleanPath = ltrim($cleanPath, '/');
    
    // Remove "sellapp/" prefix if present (with or without leading slash)
    $cleanPath = preg_replace('#^sellapp/#', '', $cleanPath);
    
    // Build the file path - should now be "assets/css/styles.css"
    $filePath = __DIR__ . '/' . $cleanPath;
    
    // Debug logging (only in development)
    if (defined('APP_ENV') && APP_ENV === 'local') {
        error_log("Static file request: {$requestPath} -> {$cleanPath} -> {$filePath}");
        error_log("File exists: " . (file_exists($filePath) ? 'yes' : 'no'));
    }
    
    // If file doesn't exist, try alternative paths
    if (!file_exists($filePath) || !is_file($filePath)) {
        // Try extracting just the assets part from original path
        if (preg_match('#(?:/sellapp/)?(assets/.*)$#', $requestPath, $matches)) {
            $filePath = __DIR__ . '/' . $matches[1];
        }
    }
    
    // Check if file exists and is readable
    if (file_exists($filePath) && is_file($filePath) && is_readable($filePath)) {
        // Debug logging
        if (defined('APP_ENV') && APP_ENV === 'local') {
            error_log("Serving static file: {$filePath}");
        }
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
        // File doesn't exist - return 404 with debug info
        http_response_code(404);
        header('Content-Type: text/plain');
        
        // Always show debug info to help troubleshoot
        $debugInfo = [
            'request_uri' => $requestUri,
            'request_path' => $requestPath,
            'clean_path' => $cleanPath ?? 'N/A',
            'file_path' => $filePath,
            'file_exists' => file_exists($filePath) ? 'yes' : 'no',
            'is_file' => is_file($filePath) ? 'yes' : 'no',
            'is_readable' => is_readable($filePath) ? 'yes' : 'no',
            'base_dir' => __DIR__
        ];
        
        echo "File not found\n\n";
        echo "Debug info:\n";
        foreach ($debugInfo as $key => $value) {
            echo "  {$key}: {$value}\n";
        }
        exit;
    }
}

// Load Composer autoloader (if available)
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    
    // Verify that Firebase JWT is actually loaded
    if (!class_exists('Firebase\JWT\JWT')) {
        // Autoloader exists but JWT library is missing - composer install may have failed
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Firebase JWT library not found. Please run: composer install'
            ]);
            exit;
        } else {
            die('
            <!DOCTYPE html>
            <html>
            <head>
                <title>Dependencies Missing</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                    .error { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    h1 { color: #d32f2f; }
                    code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>⚠️ Dependencies Missing</h1>
                    <p>The Firebase JWT library is not installed.</p>
                    <p>Please run the following command on your server:</p>
                    <p><code>composer install</code></p>
                    <p style="margin-top: 20px; color: #666; font-size: 14px;">
                        This will install the Firebase JWT library and other required dependencies.
                    </p>
                </div>
            </body>
            </html>');
        }
    }
} else {
    // Show helpful error if vendor directory is missing
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Composer dependencies not installed. Please run: composer install'
        ]);
        exit;
    } else {
        die('
        <!DOCTYPE html>
        <html>
        <head>
            <title>Dependencies Missing</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .error { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #d32f2f; }
                code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>⚠️ Dependencies Missing</h1>
                <p>The required PHP libraries are not installed.</p>
                <p>Please run the following command on your server:</p>
                <p><code>composer install</code></p>
                <p style="margin-top: 20px; color: #666; font-size: 14px;">
                    This will install the Firebase JWT library and other required dependencies.
                </p>
            </div>
        </body>
        </html>');
    }
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

// Initialize Router (use statement must be outside try-catch)
use App\Core\Router;

// Wrap entire bootstrap in try-catch to catch any fatal errors
try {
    // Load application configuration
    require_once __DIR__ . '/config/app.php';
    
    // Load database configuration
    require_once __DIR__ . '/config/database.php';
    
    // Load Cloudinary storage helper AFTER database is loaded
    require_once __DIR__ . '/app/Helpers/CloudinaryStorage.php';
    
    $router = new Router();
    
    // Load routes
    require_once __DIR__ . '/routes/web.php';
    
    // Dispatch request
    $router->dispatch();
} catch (\Exception $e) {
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log the exception
    // Log to Cloudinary instead of file system
    try {
        if (class_exists('CloudinaryStorage')) {
            CloudinaryStorage::logError("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            CloudinaryStorage::logError("Stack trace: " . $e->getTraceAsString());
        } else {
            error_log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    } catch (\Exception $logError) {
        error_log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
    }
    
    $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    
    // Handle database connection errors - use 503 for service unavailable
    if (strpos($e->getMessage(), 'Database connection failed') !== false || 
        strpos($e->getMessage(), 'SQLSTATE') !== false ||
        $e instanceof \PDOException) {
        
        if (!headers_sent()) {
            http_response_code(503); // Service Unavailable for database errors
            header('Retry-After: 60'); // Suggest retry after 60 seconds
            
            if ($isApiRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Service Unavailable',
                    'message' => 'Database connection failed. The service is temporarily unavailable.',
                    'type' => 'database_error'
                ]);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html>
<html>
<head>
    <title>Service Unavailable</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; }
        p { color: #666; line-height: 1.6; }
        ul { text-align: left; display: inline-block; }
    </style>
</head>
<body>
    <div class="error">
        <h1>Service Unavailable</h1>
        <p>The server is temporarily unable to service your request due to a database connection issue.</p>
        <p>Please try again in a few moments. If the problem persists, please contact the administrator.</p>
        <p style="margin-top: 20px; font-size: 12px; color: #999;">
            Error details have been logged for the administrator.
        </p>
    </div>
</body>
</html>';
            }
        }
        exit;
    } else {
        // For other exceptions, use 500 Internal Server Error
        if (!headers_sent()) {
            http_response_code(500);
            
            if ($isApiRequest) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Internal Server Error',
                    'message' => 'An unexpected error occurred. Please try again later.'
                ]);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html>
<html>
<head>
    <title>Internal Server Error</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; }
        p { color: #666; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="error">
        <h1>Internal Server Error</h1>
        <p>An unexpected error occurred while processing your request.</p>
        <p>Please try again later. If the problem persists, please contact the administrator.</p>
    </div>
</body>
</html>';
            }
        }
        exit;
    }
} catch (\Throwable $e) {
    // Catch any other throwable (PHP 7+) including bootstrap errors
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("Uncaught throwable: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    
    if (!headers_sent()) {
        http_response_code(503);
        
        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Service Unavailable',
                'message' => 'The server encountered an error and is temporarily unable to service your request.',
                'type' => 'bootstrap_error'
            ]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Service Unavailable</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; }
        p { color: #666; line-height: 1.6; }
        .details { margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px; font-size: 12px; color: #999; text-align: left; }
    </style>
</head>
<body>
    <div class="error">
        <h1>Service Unavailable</h1>
        <p>The server is temporarily unable to service your request.</p>
        <p>Please try again later. If the problem persists, please contact the administrator.</p>
        <div class="details">
            <strong>Error Details:</strong><br>
            ' . htmlspecialchars($e->getMessage()) . '<br>
            <small>File: ' . htmlspecialchars(basename($e->getFile())) . ' (Line: ' . $e->getLine() . ')</small>
        </div>
    </div>
</body>
</html>';
        }
    }
    exit;
} catch (\Error $e) {
    // Catch PHP 7+ Error class (separate from Exception)
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("PHP Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    $isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    
    if (!headers_sent()) {
        http_response_code(503);
        
        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Service Unavailable',
                'message' => 'A PHP error occurred during application initialization.'
            ]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Service Unavailable</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; }
        p { color: #666; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="error">
        <h1>Service Unavailable</h1>
        <p>The server encountered a PHP error during initialization.</p>
        <p>Please contact the administrator with this information.</p>
    </div>
</body>
</html>';
        }
    }
    exit;
}

