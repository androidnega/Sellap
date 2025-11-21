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
            // Check if JWT class is available
            if (!class_exists('Firebase\JWT\JWT')) {
                throw new \Exception('Firebase JWT library not found. Please run: composer install');
            }
            
            $this->userModel = new User();
            $this->secret = getenv('JWT_SECRET') ?: JWT_SECRET;
        } catch (\Exception $e) {
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

