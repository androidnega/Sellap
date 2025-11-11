<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

/**
 * Reset Job Model
 * Manages async job tracking for file cleanup and notifications
 */
class ResetJob {
    private $conn;
    private $table = 'reset_jobs';

    public function __construct() {
        try {
            $this->conn = \Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a new reset job
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (
            admin_action_id, 
            job_type, 
            status, 
            details,
            max_retries
        ) VALUES (
            :admin_action_id, 
            :job_type, 
            :status, 
            :details,
            :max_retries
        )";
        
        $stmt = $this->conn->prepare($sql);
        $params = [
            'admin_action_id' => $data['admin_action_id'],
            'job_type' => $data['job_type'],
            'status' => $data['status'] ?? 'pending',
            'details' => isset($data['details']) ? json_encode($data['details']) : null,
            'max_retries' => $data['max_retries'] ?? 3
        ];
        
        $stmt->execute($params);
        return $this->conn->lastInsertId();
    }

    /**
     * Update reset job
     */
    public function update($id, $data) {
        $updates = [];
        $params = ['id' => $id];
        
        $allowedFields = [
            'status', 'details', 'error_message', 'retry_count',
            'started_at', 'completed_at'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['started_at', 'completed_at']) && $data[$field] === null) {
                    $updates[] = "{$field} = NULL";
                } elseif (in_array($field, ['started_at', 'completed_at'])) {
                    $updates[] = "{$field} = :{$field}";
                    $params[$field] = $data[$field];
                } elseif ($field === 'details' && is_array($data[$field])) {
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
     * Get pending jobs by type
     */
    public function getPendingJobs($jobType = null, $limit = 10) {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'pending'";
        $params = [];
        
        if ($jobType) {
            $sql .= " AND job_type = ?";
            $params[] = $jobType;
        }
        
        $sql .= " ORDER BY created_at ASC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find job by ID
     */
    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

