<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class RestorePoint {
    private $db;
    private $table = 'restore_points';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a restore point
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
            (company_id, name, description, backup_id, backup_file_path, snapshot_data, 
             total_records, tables_included, created_by, created_at)
            VALUES (:company_id, :name, :description, :backup_id, :backup_file_path, :snapshot_data,
                    :total_records, :tables_included, :created_by, NOW())
        ");
        
        $stmt->execute([
            'company_id' => $data['company_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'backup_id' => $data['backup_id'] ?? null,
            'backup_file_path' => $data['backup_file_path'] ?? null,
            'snapshot_data' => isset($data['snapshot_data']) ? json_encode($data['snapshot_data']) : null,
            'total_records' => $data['total_records'] ?? null,
            'tables_included' => $data['tables_included'] ?? null,
            'created_by' => $data['created_by'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Get restore points for a company
     */
    public function getForCompany($companyId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT 
                rp.*,
                u.username as created_by_name,
                u.full_name as created_by_full_name,
                b.file_name as backup_file_name
            FROM {$this->table} rp
            LEFT JOIN users u ON rp.created_by = u.id
            LEFT JOIN backups b ON rp.backup_id = b.id
            WHERE rp.company_id = :company_id
            ORDER BY rp.created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields
        foreach ($results as &$result) {
            if (!empty($result['snapshot_data'])) {
                $result['snapshot_data'] = json_decode($result['snapshot_data'], true);
            }
        }
        
        return $results;
    }

    /**
     * Get restore point by ID
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
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['snapshot_data'])) {
            $result['snapshot_data'] = json_decode($result['snapshot_data'], true);
        }
        
        return $result;
    }

    /**
     * Update restore point
     */
    public function update($id, array $data) {
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = ['name', 'description', 'restored_at', 'restore_count'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Increment restore count
     */
    public function incrementRestoreCount($id) {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET restore_count = restore_count + 1,
                restored_at = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Delete restore point
     */
    public function delete($id, $companyId) {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE id = :id AND company_id = :company_id
        ");
        
        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }

    /**
     * Get restore point statistics
     */
    public function getStats($companyId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_restore_points,
                MAX(created_at) as latest_restore_point,
                SUM(restore_count) as total_restores
            FROM {$this->table}
            WHERE company_id = :company_id
        ");
        
        $stmt->execute(['company_id' => $companyId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

