<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class User {
    private $conn;
    private $table = 'users';

    public function __construct() {
        try {
            $this->conn = \Database::getInstance()->getConnection();
        } catch (Exception $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a new user
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (company_id, unique_id, username, email, phone_number, full_name, password, role, is_active)
                VALUES (:company_id, :unique_id, :username, :email, :phone_number, :full_name, :password, :role, :is_active)";
        $stmt = $this->conn->prepare($sql);
        
        $params = [
            'company_id' => $data['company_id'] ?? null,
            'unique_id' => $data['unique_id'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'full_name' => $data['full_name'] ?? $data['username'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'salesperson',
            'is_active' => $data['is_active'] ?? 1
        ];
        
        return $stmt->execute($params);
    }

    /**
     * Find user by ID
     */
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find user by ID and company (Multi-tenant safe)
     * @param int $id User ID
     * @param int $companyId Company ID for multi-tenant isolation
     */
    public function find($id, $companyId = null) {
        if ($companyId !== null) {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id AND company_id = :company_id LIMIT 1");
            $stmt->execute(['id' => $id, 'company_id' => $companyId]);
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find user by username or email
     */
    public function findByUsernameOrEmail($value) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE username = :v OR email = :v LIMIT 1");
        $stmt->execute(['v' => $value]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find user by unique_id
     */
    public function findByUniqueId($uniqueId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE unique_id = :unique_id LIMIT 1");
        $stmt->execute(['unique_id' => $uniqueId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all users
     */
    public function all() {
        return $this->conn->query("SELECT id, company_id, unique_id, username, email, phone_number, full_name, role, is_active, created_at 
                                   FROM {$this->table} ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all users by company (Multi-tenant filtering)
     */
    public function allByCompany($company_id) {
        $stmt = $this->conn->prepare("SELECT id, company_id, unique_id, username, email, phone_number, full_name, role, is_active, created_at 
                                       FROM {$this->table} WHERE company_id = :company_id ORDER BY created_at DESC");
        $stmt->execute(['company_id' => $company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update user information (Multi-tenant safe)
     * @param int $id User ID
     * @param array $data Update data
     * @param int|null $companyId Optional company ID for multi-tenant isolation
     */
    public function update($id, $data, $companyId = null) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $params = $data;
        $params['id'] = $id;
        
        // Add company filter for multi-tenant isolation if provided
        if ($companyId !== null) {
            $sql .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete user (Multi-tenant safe)
     * @param int $id User ID
     * @param int|null $companyId Optional company ID for multi-tenant isolation
     */
    public function delete($id, $companyId = null) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $params = ['id' => $id];
        
        // Add company filter for multi-tenant isolation if provided
        if ($companyId !== null) {
            $sql .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get company name by company_id
     */
    public function getCompanyName($company_id) {
        if (!$company_id) return '';
        $stmt = $this->conn->prepare("SELECT name FROM companies WHERE id = :company_id LIMIT 1");
        $stmt->execute(['company_id' => $company_id]);
        $row = $stmt->fetch();
        return $row ? $row['name'] : '';
    }
}

