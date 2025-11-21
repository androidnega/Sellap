<?php

namespace App\Core;

/**
 * Simple Router Class for cPanel Hosting
 * Handles GET, POST, PUT, DELETE requests with dynamic parameters
 */
class Router {
    private $routes = [];

    /**
     * Register GET route
     */
    public function get($path, $callback) {
        $this->routes['GET'][$path] = $callback;
    }

    /**
     * Register POST route
     */
    public function post($path, $callback) {
        $this->routes['POST'][$path] = $callback;
    }

    /**
     * Register PUT route
     */
    public function put($path, $callback) {
        $this->routes['PUT'][$path] = $callback;
    }

    /**
     * Register DELETE route
     */
    public function delete($path, $callback) {
        $this->routes['DELETE'][$path] = $callback;
    }

    /**
     * Dispatch current request
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Support method override via _method parameter (common workaround for servers that don't support DELETE/PUT)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $overrideMethod = strtoupper($_POST['_method']);
            if (in_array($overrideMethod, ['PUT', 'DELETE', 'PATCH'])) {
                $method = $overrideMethod;
            }
        }
        
        // Also check for X-HTTP-Method-Override header
        if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $overrideMethod = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            if (in_array($overrideMethod, ['PUT', 'DELETE', 'PATCH'])) {
                $method = $overrideMethod;
            }
        }
        
        // Handle CORS preflight requests
        if ($method === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            http_response_code(200);
            exit;
        }
        
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Aggressively remove kabz_events from URI in all forms (completely remove this path)
        $uri = preg_replace('#/kabz_events(/|$)#', '/', $uri);
        $uri = preg_replace('#^/kabz_events#', '', $uri);
        $uri = str_replace('/kabz_events', '', $uri);
        $uri = str_replace('kabz_events/', '', $uri);
        $uri = str_replace('kabz_events', '', $uri);
        
        // Remove base directory from URI if running in subdirectory
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        // Aggressively remove kabz_events from script name
        $scriptName = preg_replace('#/kabz_events(/|$)#', '/', $scriptName);
        $scriptName = preg_replace('#^/kabz_events#', '', $scriptName);
        $scriptName = str_replace('/kabz_events', '', $scriptName);
        $scriptName = str_replace('kabz_events/', '', $scriptName);
        $scriptName = str_replace('kabz_events', '', $scriptName);
        
        if ($scriptName !== '/' && $scriptName !== '.' && !empty($scriptName)) {
            $uri = str_replace($scriptName, '', $uri);
        }
        
        // Only remove /sellapp if BASE_URL_PATH is not empty (i.e., we're in a subdirectory)
        // If BASE_URL_PATH is empty (root domain), don't strip /sellapp as it might be a valid route
        $basePath = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
        if (!empty($basePath) && $basePath !== '/') {
            // We're in a subdirectory, so remove the base path from URI
            $basePathClean = trim($basePath, '/');
            if (preg_match('#^/?'.$basePathClean.'/(.+)$#', $uri, $matches)) {
                $uri = $matches[1];
            } elseif (preg_match('#^/?'.$basePathClean.'$#', $uri)) {
                $uri = '';
            }
            
            // Additional cleanup for any remaining base path references
            $uri = preg_replace('#^'.$basePathClean.'/#', '/', $uri);
            $uri = preg_replace('#^'.$basePathClean.'$#', '', $uri);
        }
        
        // Legacy: Remove /sellapp only if we're NOT on root domain
        // This handles cases where old URLs with /sellapp are accessed
        if (!empty($basePath)) {
            // Only remove /sellapp if we're in a subdirectory setup
            if (preg_match('#^/?sellapp/(.+)$#', $uri, $matches)) {
                $uri = $matches[1];
            } elseif (preg_match('#^/?sellapp$#', $uri)) {
                $uri = '';
            }
            $uri = preg_replace('#^/sellapp/#', '/', $uri);
            $uri = preg_replace('#^sellapp/#', '', $uri);
        }
        
        // Final cleanup - ensure no kabz_events remains
        $uri = preg_replace('#/kabz_events(/|$)#', '/', $uri);
        $uri = str_replace('kabz_events', '', $uri);
        
        $uri = trim($uri, '/');

        // First pass: Check static routes (no parameters) - these must match exactly first
        foreach ($this->routes[$method] ?? [] as $route => $callback) {
            // Normalize route
            $route = trim($route, '/');
            
            // Match empty routes (homepage)
            if ($route === '' && $uri === '') {
                return call_user_func($callback);
            }
            
            // Check if route has dynamic parameters
            $hasParams = preg_match('/\{(\w+)\}/', $route);
            
            // For static routes (no parameters), do exact string match
            if (!$hasParams && $route === $uri) {
                return call_user_func($callback);
            }
        }
        
        // Second pass: Check dynamic routes (with parameters) only if no static route matched
        foreach ($this->routes[$method] ?? [] as $route => $callback) {
            // Normalize route
            $route = trim($route, '/');
            
            // Check if route has dynamic parameters
            $hasParams = preg_match('/\{(\w+)\}/', $route);
            
            // For dynamic routes, use regex pattern matching
            if ($hasParams) {
                // Convert {param} to regex pattern and escape forward slashes
                // Allow numeric IDs as well - use \d+ for numeric params or allow alphanumeric
                $pattern = preg_replace('/\{(\w+)\}/', '([0-9]+|[a-zA-Z0-9_-]+)', $route);
                $pattern = str_replace('/', '\/', $pattern); // Escape forward slashes for regex
                
                if (preg_match("#^$pattern$#", $uri, $matches)) {
                    array_shift($matches); // Remove full match
                    return call_user_func_array($callback, $matches);
                }
            }
        }

        // 404 Not Found
        http_response_code(404);
        
        // For API requests, return JSON 404
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Route not found',
                'method' => $method,
                'uri' => $uri,
                'available_routes' => array_keys($this->routes[$method] ?? [])
            ]);
        } else {
            // Show nice "Oops!" page with option to go to homepage
            header('Content-Type: text/html; charset=utf-8');
            $homeUrl = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
            // Ensure kabz_events is not in home URL
            $homeUrl = preg_replace('#/kabz_events(/|$)#', '/', $homeUrl);
            $homeUrl = str_replace('kabz_events', '', $homeUrl);
            // Normalize: empty string means root
            if ($homeUrl === '/') {
                $homeUrl = '';
            }
            echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .container {
            text-align: center;
            animation: fadeIn 0.6s ease-in;
            max-width: 600px;
            padding: 2rem;
        }
        .oops {
            font-size: 12rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 1rem;
            color: #1a1a1a;
            animation: bounce 1s ease-in-out;
        }
        .message {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            color: #666;
        }
        .home-button {
            display: inline-block;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            background: #667eea;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .home-button:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .home-button:active {
            transform: translateY(0);
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="oops">Oops!</div>
        <div class="message">This page doesn\'t exist</div>
        <a href="' . htmlspecialchars($homeUrl) . '" class="home-button">Go to Homepage</a>
    </div>
</body>
</html>';
        }
    }

    /**
     * Load routes from file
     */
    public function loadRoutes($file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

