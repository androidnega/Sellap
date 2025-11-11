<?php

namespace App\Services;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class VersioningService {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create version entry for a data change
     * 
     * @param int $companyId
     * @param string $tableName
     * @param int $recordId
     * @param string $action 'create', 'update', 'delete'
     * @param array|null $oldData
     * @param array|null $newData
     * @param int|null $userId
     * @param string|null $ipAddress
     * @return int Version ID
     */
    public function createVersion($companyId, $tableName, $recordId, $action, $oldData = null, $newData = null, $userId = null, $ipAddress = null) {
        try {
            if ($ipAddress === null) {
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            }

            $stmt = $this->db->prepare("
                INSERT INTO audit_versions
                (company_id, table_name, record_id, action, old_data, new_data, user_id, ip_address, created_at)
                VALUES (:company_id, :table_name, :record_id, :action, :old_data, :new_data, :user_id, :ip_address, NOW())
            ");
            
            $stmt->execute([
                'company_id' => $companyId,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'action' => $action,
                'old_data' => $oldData ? json_encode($oldData) : null,
                'new_data' => $newData ? json_encode($newData) : null,
                'user_id' => $userId,
                'ip_address' => $ipAddress
            ]);

            return $this->db->lastInsertId();
        } catch (\Exception $e) {
            error_log("VersioningService::createVersion error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get version history for a record
     * 
     * @param string $tableName
     * @param int $recordId
     * @param int|null $companyId
     * @return array
     */
    public function getVersionHistory($tableName, $recordId, $companyId = null) {
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
            FROM audit_versions av
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
     * Rollback a record to a previous version
     * 
     * @param int $versionId
     * @param int $companyId
     * @param int $userId
     * @return bool
     */
    public function rollbackToVersion($versionId, $companyId, $userId) {
        try {
            // Get version data
            $stmt = $this->db->prepare("
                SELECT * FROM audit_versions
                WHERE id = :version_id AND company_id = :company_id
            ");
            $stmt->execute([
                'version_id' => $versionId,
                'company_id' => $companyId
            ]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$version) {
                throw new \Exception('Version not found');
            }

            if ($version['action'] === 'delete') {
                // Restore deleted record
                $oldData = json_decode($version['old_data'], true);
                if ($oldData) {
                    $columns = array_keys($oldData);
                    $values = array_values($oldData);
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    
                    $stmt = $this->db->prepare("
                        INSERT INTO {$version['table_name']} (" . implode(', ', $columns) . ")
                        VALUES ({$placeholders})
                    ");
                    $stmt->execute($values);
                }
            } elseif ($version['action'] === 'update') {
                // Restore to old data
                $oldData = json_decode($version['old_data'], true);
                if ($oldData) {
                    $setParts = [];
                    $values = [];
                    foreach ($oldData as $key => $value) {
                        $setParts[] = "{$key} = ?";
                        $values[] = $value;
                    }
                    $values[] = $version['record_id'];
                    $values[] = $companyId;

                    $stmt = $this->db->prepare("
                        UPDATE {$version['table_name']}
                        SET " . implode(', ', $setParts) . "
                        WHERE id = ? AND company_id = ?
                    ");
                    $stmt->execute($values);
                }
            }

            // Log rollback action
            $this->createVersion(
                $companyId,
                $version['table_name'],
                $version['record_id'],
                'update',
                null, // Current state
                $oldData ?? json_decode($version['old_data'], true),
                $userId,
                null
            );

            return true;
        } catch (\Exception $e) {
            error_log("VersioningService::rollbackToVersion error: " . $e->getMessage());
            return false;
        }
    }
}

