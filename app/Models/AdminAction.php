<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

/**
 * Admin Action Model
 * Manages audit logging for destructive operations
 */
class AdminAction {
    private $conn;
    private $table = 'admin_actions';

    public function __construct() {
        try {
            $this->conn = \Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a new admin action record
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (
            admin_user_id, 
            action_type, 
            target_company_id, 
            dry_run, 
            payload, 
            backup_reference,
            status,
            started_at
        ) VALUES (
            :admin_user_id, 
            :action_type, 
            :target_company_id, 
            :dry_run, 
            :payload, 
            :backup_reference,
            :status,
            NOW()
        )";
        
        $stmt = $this->conn->prepare($sql);
        $params = [
            'admin_user_id' => $data['admin_user_id'],
            'action_type' => $data['action_type'],
            'target_company_id' => $data['target_company_id'] ?? null,
            'dry_run' => $data['dry_run'] ?? 0,
            'payload' => $data['payload'] ?? null,
            'backup_reference' => $data['backup_reference'] ?? null,
            'status' => $data['status'] ?? 'pending'
        ];
        
        $stmt->execute($params);
        return $this->conn->lastInsertId();
    }

    /**
     * Update admin action record
     */
    public function update($id, $data) {
        $updates = [];
        $params = ['id' => $id];
        
        $allowedFields = [
            'row_counts', 'backup_reference', 'status', 'error_message', 
            'completed_at', 'payload'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'completed_at' && $data[$field] === null) {
                    $updates[] = "{$field} = NULL";
                } elseif ($field === 'completed_at') {
                    $updates[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                } elseif (in_array($field, ['row_counts', 'payload']) && is_array($data[$field])) {
                    $updates[] = "{$field} = :{$field}";
                    $params[$field] = json_encode($data[$field]);
                } else {
                    $updates[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                }
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Find admin action by ID
     */
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get admin actions by admin user
     */
    public function findByAdmin($adminUserId, $limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table} 
            WHERE admin_user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$adminUserId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get admin actions by company
     */
    public function findByCompany($companyId, $limit = 50) {
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table} 
            WHERE target_company_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$companyId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

