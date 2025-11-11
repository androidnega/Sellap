<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

/**
 * Staff Model
 * Manages salespeople and technicians within a company
 */
class Staff {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get all staff members for a specific company
     * Only returns salespeople and technicians (not managers or admins)
     */
    public function allByCompany($company_id) {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                unique_id,
                username,
                email,
                phone_number,
                full_name,
                role,
                is_active as status,
                created_at,
                updated_at
            FROM users 
            WHERE company_id = ? 
            AND role IN ('salesperson', 'technician')
            ORDER BY created_at DESC
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a specific staff member (scoped to company)
     */
    public function find($id, $company_id) {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                unique_id,
                username,
                email,
                phone_number,
                full_name,
                role,
                is_active as status,
                company_id,
                created_at
            FROM users 
            WHERE id = ? 
            AND company_id = ?
            AND role IN ('salesperson', 'technician')
        ");
        $stmt->execute([$id, $company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new staff member
     */
    public function create($data) {
        // Generate unique ID
        $unique_id = 'USR' . strtoupper(uniqid());
        
        $stmt = $this->db->prepare("
            INSERT INTO users (
                unique_id,
                username,
                email,
                phone_number,
                full_name,
                password,
                role,
                company_id,
                is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $unique_id,
            $data['username'] ?? $data['email'], // Use email as username if not provided
            $data['email'],
            $data['phone_number'] ?? null,
            $data['full_name'],
            $data['password'],
            $data['role'],
            $data['company_id'],
            $data['status'] == 'active' ? 1 : 0
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Update staff member details
     */
    public function update($id, $data, $company_id) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET 
                full_name = ?,
                email = ?,
                phone_number = ?,
                role = ?,
                is_active = ?
            WHERE id = ? 
            AND company_id = ?
            AND role IN ('salesperson', 'technician')
        ");
        
        return $stmt->execute([
            $data['full_name'],
            $data['email'],
            $data['phone_number'] ?? null,
            $data['role'],
            $data['status'] == 'active' ? 1 : 0,
            $id,
            $company_id
        ]);
    }

    /**
     * Delete a staff member (scoped to company)
     */
    public function delete($id, $company_id) {
        $stmt = $this->db->prepare("
            DELETE FROM users 
            WHERE id = ? 
            AND company_id = ?
            AND role IN ('salesperson', 'technician')
        ");
        return $stmt->execute([$id, $company_id]);
    }

    /**
     * Search staff by name or email
     */
    public function search($company_id, $query) {
        $searchTerm = "%{$query}%";
        $stmt = $this->db->prepare("
            SELECT 
                id,
                unique_id,
                username,
                email,
                phone_number,
                full_name,
                role,
                is_active as status,
                created_at
            FROM users 
            WHERE company_id = ? 
            AND role IN ('salesperson', 'technician')
            AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)
            ORDER BY created_at DESC
        ");
        $stmt->execute([$company_id, $searchTerm, $searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total staff for a company
     */
    public function countByCompany($company_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total 
            FROM users 
            WHERE company_id = ? 
            AND role IN ('salesperson', 'technician')
        ");
        $stmt->execute([$company_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Reset password for a staff member
     */
    public function resetPassword($id, $company_id, $newPassword = null) {
        // Generate a default password if none provided
        if (!$newPassword) {
            $newPassword = $this->generateDefaultPassword();
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND company_id = ? 
            AND role IN ('salesperson', 'technician')
        ");
        
        $result = $stmt->execute([$hashedPassword, $id, $company_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            return $newPassword; // Return the plain text password for display
        }
        
        return false;
    }

    /**
     * Generate a secure default password
     */
    private function generateDefaultPassword() {
        // Generate a secure 8-character password
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * Check if a staff member exists and belongs to the company
     */
    public function exists($id, $company_id) {
        $stmt = $this->db->prepare("
            SELECT id 
            FROM users 
            WHERE id = ? AND company_id = ? 
            AND role IN ('salesperson', 'technician')
            LIMIT 1
        ");
        $stmt->execute([$id, $company_id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Check if email already exists in the company (Multi-tenant safe)
     * @param string $email Email to check
     * @param int|null $excludeId Optional user ID to exclude from check (for updates)
     * @param int|null $companyId Optional company ID for multi-tenant isolation (MANDATORY)
     */
    public function emailExists($email, $excludeId = null, $companyId = null) {
        $sql = "SELECT id FROM users WHERE email = ?";
        $params = [$email];
        
        // Company filter (MANDATORY for multi-tenant isolation)
        if ($companyId !== null) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Check if username already exists in the company (Multi-tenant safe)
     * @param string $username Username to check
     * @param int|null $excludeId Optional user ID to exclude from check (for updates)
     * @param int|null $companyId Optional company ID for multi-tenant isolation (MANDATORY)
     */
    public function usernameExists($username, $excludeId = null, $companyId = null) {
        $sql = "SELECT id FROM users WHERE username = ?";
        $params = [$username];
        
        // Company filter (MANDATORY for multi-tenant isolation)
        if ($companyId !== null) {
            $sql .= " AND company_id = ?";
            $params[] = $companyId;
        }
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }
}

