<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService {
    private $userModel;
    private $secret;
    private $algo = 'HS256';
    private $ttl = 3600; // 1 hour

    public function __construct() {
        try {
            // Check if vendor/autoload.php was loaded
            if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
                throw new \Exception('Composer dependencies not installed. The vendor/autoload.php file is missing. Please run "composer install" on your server.');
            }
            
            // Check if JWT class is available
            if (!class_exists('Firebase\JWT\JWT')) {
                // Try to load the autoloader if it wasn't loaded
                if (!class_exists('Firebase\JWT\JWT')) {
                    throw new \Exception('Firebase JWT library not found. Please run "composer install" on your server to install required dependencies.');
                }
            }
            
            // Ensure config is loaded
            if (!defined('JWT_SECRET')) {
                // Try to load config if not already loaded
                if (file_exists(__DIR__ . '/../../config/app.php')) {
                    require_once __DIR__ . '/../../config/app.php';
                }
            }
            
            // Get JWT secret with fallback
            if (defined('JWT_SECRET')) {
                $this->secret = getenv('JWT_SECRET') ?: JWT_SECRET;
            } else {
                $this->secret = getenv('JWT_SECRET') ?: 'your_secret_key_change_in_production';
                error_log('WARNING: JWT_SECRET constant not defined. Using fallback secret. Please set JWT_SECRET in config/app.php or environment variable.');
            }
            
            $this->userModel = new User();
        } catch (\Exception $e) {
            error_log('AuthService constructor error: ' . $e->getMessage());
            error_log('AuthService constructor error trace: ' . $e->getTraceAsString());
            throw new \Exception('Failed to initialize authentication service: ' . $e->getMessage());
        }
    }

    /**
     * Authenticate user and generate JWT token
     */
    public function login(string $username, string $password): array {
        $user = $this->userModel->findByUsernameOrEmail($username);
        
        if (!$user || !password_verify($password, $user['password'])) {
            throw new \Exception('Invalid credentials');
        }
        
        if (!$user['is_active']) {
            throw new \Exception('Account inactive');
        }

        $companyName = '';
        if (in_array($user['role'], ['manager', 'admin'])) {
            $companyName = $this->userModel->getCompanyName($user['company_id'] ?? null);
        }
        $token = $this->generateToken($user, $companyName);
        
        return [
            'token' => $token,
            'user'  => [
                'id' => $user['id'],
                'unique_id' => $user['unique_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'company_id' => $user['company_id'] ?? null,
                'company_name' => $companyName
            ]
        ];
    }

    /**
     * Generate JWT token (with company_id for multi-tenant support)
     */
    private function generateToken(array $user, $companyName = ''): string {
        $payload = [
            'iss' => APP_URL,
            'sub' => $user['id'],
            'unique_id' => $user['unique_id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'company_id' => $user['company_id'] ?? null,
            'company_name' => $companyName,
            'iat' => time(),
            'exp' => time() + $this->ttl
        ];
        
        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Validate JWT token
     */
    public function validateToken(string $token): object {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (\Exception $e) {
            throw new \Exception('Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * Register new user
     */
    public function register(array $data): array {
        // Check if username or email already exists
        $existing = $this->userModel->findByUsernameOrEmail($data['username']);
        if ($existing) {
            throw new \Exception('Username or email already exists');
        }

        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        
        // Generate unique ID
        $data['unique_id'] = 'USR' . strtoupper(uniqid());
        
        // Set default role if not provided
        if (!isset($data['role'])) {
            $data['role'] = 'salesperson';
        }

        if ($this->userModel->create($data)) {
            return ['success' => true, 'message' => 'User registered successfully'];
        }
        
        throw new \Exception('Failed to register user');
    }
}

