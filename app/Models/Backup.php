<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class Backup {
    private $db;
    private $table = 'backups';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create backup record
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
            (company_id, file_name, file_path, file_size, status, record_count, format, created_by, created_at)
            VALUES (:company_id, :file_name, :file_path, :file_size, :status, :record_count, :format, :created_by, NOW())
        ");
        
        $stmt->execute([
            'company_id' => $data['company_id'],
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'file_size' => $data['file_size'] ?? null,
            'status' => $data['status'] ?? 'completed',
            'record_count' => $data['record_count'] ?? null,
            'format' => $data['format'] ?? 'json',
            'created_by' => $data['created_by'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Get backups for a company
     */
    public function getForCompany($companyId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT 
                b.*,
                u.username as created_by_name,
                u.full_name as created_by_full_name
            FROM {$this->table} b
            LEFT JOIN users u ON b.created_by = u.id
            WHERE b.company_id = :company_id
            ORDER BY b.created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update backup status
     */
    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET status = :status
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $id,
            'status' => $status
        ]);
    }

    /**
     * Get backup by ID
     */
    public function find($id, $companyId = null) {
        $where = "id = :id";
        $params = ['id' => $id];
        
        if ($companyId) {
            $where .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$where}");
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Delete backup record and file
     */
    public function delete($id, $companyId) {
        // Get backup info first
        $backup = $this->find($id, $companyId);
        
        if (!$backup) {
            return false;
        }

        // Delete file if exists
        if (!empty($backup['file_path']) && file_exists($backup['file_path'])) {
            @unlink($backup['file_path']);
        }

        // Delete record
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id AND company_id = :company_id");
        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }
}

