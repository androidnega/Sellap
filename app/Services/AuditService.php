<?php

namespace App\Services;

use PDO;

require_once __DIR__ . '/../../config/database.php';

class AuditService {
    private $db;
    private static $secretKey = null;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get or generate audit secret key from system settings
     */
    private function getSecretKey() {
        if (self::$secretKey !== null) {
            return self::$secretKey;
        }

        // Try to get from system_settings
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'audit_secret_key' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['setting_value'])) {
                self::$secretKey = $result['setting_value'];
                return self::$secretKey;
            }
        } catch (\Exception $e) {
            // Table might not exist or key not set
        }

        // Generate and store new key
        $key = bin2hex(random_bytes(32)); // 64 character hex string
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description) 
                VALUES ('audit_secret_key', :value, 'HMAC secret for audit log signatures')
                ON DUPLICATE KEY UPDATE setting_value = :value
            ");
            $stmt->execute(['value' => $key]);
            self::$secretKey = $key;
        } catch (\Exception $e) {
            // Fallback to a default key (not ideal but functional)
            self::$secretKey = getenv('AUDIT_SECRET_KEY') ?: 'default_audit_secret_key_change_in_production';
        }

        return self::$secretKey;
    }

    /**
     * Log an audit event
     * 
     * @param int|null $companyId
     * @param int|null $userId
     * @param string $eventType e.g., 'sale.created', 'swap.completed', 'user.login'
     * @param string|null $entityType e.g., 'pos_sale', 'swap', 'repair'
     * @param int|null $entityId
     * @param array $payload Event data snapshot
     * @param string|null $ipAddress
     * @return int Audit log ID
     */
    public function logEvent($companyId, $userId, $eventType, $entityType = null, $entityId = null, $payload = [], $ipAddress = null) {
        try {
            // Get IP if not provided
            if ($ipAddress === null) {
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
                // Handle proxy headers
                if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    $ipAddress = trim($ips[0]);
                }
            }

            // Encode payload as JSON
            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Insert audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs 
                (company_id, user_id, event_type, entity_type, entity_id, payload, ip_address, created_at)
                VALUES (:company_id, :user_id, :event_type, :entity_type, :entity_id, :payload, :ip_address, NOW())
            ");
            
            $stmt->execute([
                'company_id' => $companyId,
                'user_id' => $userId,
                'event_type' => $eventType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload' => $payloadJson,
                'ip_address' => $ipAddress
            ]);

            $auditLogId = $this->db->lastInsertId();

            // Generate and store HMAC signature for tamper-evidence
            $secretKey = $this->getSecretKey();
            $signature = hash_hmac('sha256', 
                $auditLogId . '|' . $companyId . '|' . $eventType . '|' . $payloadJson . '|' . date('Y-m-d H:i:s'),
                $secretKey
            );

            // Update payload with signature
            $payloadWithSignature = json_decode($payloadJson, true);
            $payloadWithSignature['_signature'] = $signature;
            $payloadWithSignature['_signature_timestamp'] = date('Y-m-d H:i:s');
            
            $stmt = $this->db->prepare("
                UPDATE audit_logs 
                SET payload = :payload 
                WHERE id = :id
            ");
            $stmt->execute([
                'payload' => json_encode($payloadWithSignature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $auditLogId
            ]);

            // Optional: Push to real-time channel (Redis/WebSocket)
            // $this->publishToRealtimeChannel($auditLogId, $companyId, $eventType);

            return $auditLogId;
        } catch (\Exception $e) {
            error_log("AuditService::logEvent error: " . $e->getMessage());
            // Don't throw - audit logging should not break main flow
            return 0;
        }
    }

    /**
     * Static helper for quick logging
     */
    public static function log($companyId, $userId, $eventType, $entityType = null, $entityId = null, $payload = [], $ipAddress = null) {
        $service = new self();
        return $service->logEvent($companyId, $userId, $eventType, $entityType, $entityId, $payload, $ipAddress);
    }

    /**
     * Get audit logs with filters
     * 
     * @param int|null $companyId
     * @param array $filters ['event_type', 'user_id', 'entity_type', 'entity_id', 'date_from', 'date_to']
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getLogs($companyId = null, $filters = [], $limit = 100, $offset = 0) {
        $where = ['1=1'];
        $params = [];

        if ($companyId !== null) {
            $where[] = "al.company_id = :company_id";
            $params['company_id'] = $companyId;
        }

        if (!empty($filters['event_type'])) {
            $where[] = "al.event_type = :event_type";
            $params['event_type'] = $filters['event_type'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = "al.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = "al.entity_type = :entity_type";
            $params['entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $where[] = "al.entity_id = :entity_id";
            $params['entity_id'] = $filters['entity_id'];
        }

        if (!empty($filters['id'])) {
            $where[] = "al.id = :id";
            $params['id'] = $filters['id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(al.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(al.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    al.*,
                    u.username as user_name,
                    u.full_name as user_full_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY al.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            // Bind regular parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            // Bind limit and offset as integers
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON payloads
            foreach ($results as &$result) {
                if (!empty($result['payload'])) {
                    $result['payload'] = json_decode($result['payload'], true);
                }
            }

            return $results;
        } catch (\Exception $e) {
            error_log("AuditService::getLogs error: " . $e->getMessage());
            // Return empty array on error instead of crashing
            return [];
        }
    }

    /**
     * Get total count of audit logs matching filters
     * 
     * @param int|null $companyId
     * @param array $filters
     * @return int
     */
    public function getLogsCount($companyId = null, $filters = []) {
        $where = ['1=1'];
        $params = [];

        if ($companyId !== null) {
            $where[] = "al.company_id = :company_id";
            $params['company_id'] = $companyId;
        }

        if (!empty($filters['event_type'])) {
            $where[] = "al.event_type = :event_type";
            $params['event_type'] = $filters['event_type'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = "al.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = "al.entity_type = :entity_type";
            $params['entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $where[] = "al.entity_id = :entity_id";
            $params['entity_id'] = $filters['entity_id'];
        }

        if (!empty($filters['id'])) {
            $where[] = "al.id = :id";
            $params['id'] = $filters['id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE(al.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE(al.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM audit_logs al
                WHERE " . implode(' AND ', $where) . "
            ");
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return (int)($result['total'] ?? 0);
        } catch (\Exception $e) {
            error_log("AuditService::getLogsCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Verify audit log signature (tamper detection)
     * 
     * @param int $auditLogId
     * @return bool
     */
    public function verifySignature($auditLogId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, company_id, event_type, payload, created_at
                FROM audit_logs
                WHERE id = :id
            ");
            $stmt->execute(['id' => $auditLogId]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$log) {
                return false;
            }

            $payload = json_decode($log['payload'], true);
            if (!isset($payload['_signature'])) {
                return false; // No signature present
            }

            $storedSignature = $payload['_signature'];
            unset($payload['_signature']);
            $timestamp = $payload['_signature_timestamp'] ?? $log['created_at'];
            unset($payload['_signature_timestamp']);

            $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $secretKey = $this->getSecretKey();
            
            $expectedSignature = hash_hmac('sha256', 
                $log['id'] . '|' . $log['company_id'] . '|' . $log['event_type'] . '|' . $payloadJson . '|' . $timestamp,
                $secretKey
            );

            return hash_equals($storedSignature, $expectedSignature);
        } catch (\Exception $e) {
            error_log("AuditService::verifySignature error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get event statistics
     * 
     * @param int|null $companyId
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public function getEventStats($companyId = null, $dateFrom = null, $dateTo = null) {
        // Check if table exists
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'audit_logs'");
            if ($checkTable->rowCount() == 0) {
                return [];
            }
        } catch (\Exception $e) {
            error_log("AuditService::getEventStats - Could not check audit_logs table: " . $e->getMessage());
            return [];
        }
        
        try {
            $where = ['1=1'];
            $params = [];

            if ($companyId !== null) {
                $where[] = "company_id = :company_id";
                $params['company_id'] = $companyId;
            }

            if ($dateFrom) {
                $where[] = "DATE(created_at) >= :date_from";
                $params['date_from'] = $dateFrom;
            }

            if ($dateTo) {
                $where[] = "DATE(created_at) <= :date_to";
                $params['date_to'] = $dateTo;
            }

            $stmt = $this->db->prepare("
                SELECT 
                    event_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT DATE(created_at)) as active_days
                FROM audit_logs
                WHERE " . implode(' AND ', $where) . "
                GROUP BY event_type
                ORDER BY count DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("AuditService::getEventStats error: " . $e->getMessage());
            return [];
        }
    }
}

