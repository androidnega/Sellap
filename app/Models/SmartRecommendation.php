<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class SmartRecommendation {
    private $db;
    private $table = 'smart_recommendations';

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create a new recommendation
     */
    public function create(array $data) {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table}
            (company_id, title, message, type, priority, confidence, action_url, expires_at)
            VALUES (:company_id, :title, :message, :type, :priority, :confidence, :action_url, :expires_at)
        ");
        
        $stmt->execute([
            'company_id' => $data['company_id'],
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $data['type'] ?? 'general',
            'priority' => $data['priority'] ?? 'medium',
            'confidence' => $data['confidence'] ?? 0.5,
            'action_url' => $data['action_url'] ?? null,
            'expires_at' => $data['expires_at'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Get recommendations for a company
     * 
     * @param int $companyId
     * @param bool $unreadOnly
     * @param string|null $type
     * @param int $limit
     * @return array
     */
    public function getForCompany($companyId, $unreadOnly = true, $type = null, $limit = 50) {
        // Check if table exists
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE '{$this->table}'");
            if ($checkTable->rowCount() == 0) {
                return [];
            }
        } catch (\Exception $e) {
            error_log("SmartRecommendation::getForCompany - Could not check table: " . $e->getMessage());
            return [];
        }
        
        try {
            $where = "company_id = :company_id";
            $params = ['company_id' => $companyId];

            if ($unreadOnly) {
                $where .= " AND status = 'unread'";
            }

            if ($type) {
                $where .= " AND type = :type";
                $params['type'] = $type;
            }

            $where .= " AND (expires_at IS NULL OR expires_at > NOW())";

            $stmt = $this->db->prepare("
                SELECT * FROM {$this->table}
                WHERE {$where}
                ORDER BY 
                    FIELD(priority, 'high', 'medium', 'low'),
                    created_at DESC
                LIMIT :limit
            ");
            
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("SmartRecommendation::getForCompany error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark recommendation as read
     * 
     * @param int $id
     * @param int $companyId
     * @return bool
     */
    public function markAsRead($id, $companyId) {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET status = 'read'
            WHERE id = :id AND company_id = :company_id
        ");
        
        return $stmt->execute([
            'id' => $id,
            'company_id' => $companyId
        ]);
    }

    /**
     * Delete expired recommendations
     * 
     * @param int $companyId
     * @return int Number of deleted rows
     */
    public function cleanupExpired($companyId = null) {
        $where = "expires_at IS NOT NULL AND expires_at < NOW()";
        $params = [];

        if ($companyId) {
            $where .= " AND company_id = :company_id";
            $params['company_id'] = $companyId;
        }

        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$where}");
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    /**
     * Get unread count for company
     * 
     * @param int $companyId
     * @return int
     */
    public function getUnreadCount($companyId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM {$this->table}
            WHERE company_id = :company_id
            AND status = 'unread'
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute(['company_id' => $companyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['count'] ?? 0);
    }
}

