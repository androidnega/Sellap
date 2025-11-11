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
        
        // Remove base directory from URI if running in subdirectory
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/') {
            $uri = str_replace($scriptName, '', $uri);
        }
        
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
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false || 
            strpos($_SERVER['REQUEST_URI'] ?? '', '/pos/') !== false ||
            strpos($_SERVER['REQUEST_URI'] ?? '', '/dashboard/') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Route not found',
                'method' => $method,
                'uri' => $uri,
                'available_routes' => array_keys($this->routes[$method] ?? [])
            ]);
        } else {
            echo "404 - Page Not Found<br>";
            echo "Method: " . htmlspecialchars($method) . "<br>";
            echo "URI: " . htmlspecialchars($uri) . "<br>";
            if (isset($this->routes[$method])) {
                echo "Available " . $method . " routes:<br>";
                foreach (array_keys($this->routes[$method]) as $route) {
                    echo "- " . htmlspecialchars($route) . "<br>";
                }
            }
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

