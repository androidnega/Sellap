<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class AuditVersion {
    private $db;
    private $table = 'audit_versions';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get version history for a record
     */
    public function getHistory($tableName, $recordId, $companyId = null) {
        $where = "table_name = :table_name AND record_id = :record_id";
        $params = [
            'table_name' => $tableName,
            'record_id' => $recordId
        ];

        if ($companyId) {
            $where .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }

        $stmt = $this->db->prepare("
            SELECT 
                av.*,
                u.username as user_name,
                u.full_name as user_full_name
            FROM {$this->table} av
            LEFT JOIN users u ON av.user_id = u.id
            WHERE {$where}
            ORDER BY av.created_at DESC
        ");
        
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($results as &$result) {
            if (!empty($result['old_data'])) {
                $result['old_data'] = json_decode($result['old_data'], true);
            }
            if (!empty($result['new_data'])) {
                $result['new_data'] = json_decode($result['new_data'], true);
            }
        }

        return $results;
    }

    /**
     * Get versions by company and table
     */
    public function getByCompanyTable($companyId, $tableName, $limit = 100) {
        $stmt = $this->db->prepare("
            SELECT 
                av.*,
                u.username as user_name
            FROM {$this->table} av
            LEFT JOIN users u ON av.user_id = u.id
            WHERE av.company_id = :company_id AND av.table_name = :table_name
            ORDER BY av.created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':table_name', $tableName);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON
        foreach ($results as &$result) {
            if (!empty($result['old_data'])) {
                $result['old_data'] = json_decode($result['old_data'], true);
            }
            if (!empty($result['new_data'])) {
                $result['new_data'] = json_decode($result['new_data'], true);
            }
        }
        
        return $results;
    }

    /**
     * Count versions for a record
     */
    public function countVersions($tableName, $recordId, $companyId = null) {
        $where = "table_name = :table_name AND record_id = :record_id";
        $params = [
            'table_name' => $tableName,
            'record_id' => $recordId
        ];

        if ($companyId) {
            $where .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE {$where}");
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['count'] ?? 0);
    }
}

