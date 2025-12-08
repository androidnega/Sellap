<?php

namespace App\Models;

use PDO;

require_once __DIR__ . '/../../config/database.php';

/**
 * User Activity Log Model
 * Manages user login/logout activity tracking
 */
class UserActivityLog {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Log user login
     */
    public function logLogin($userId, $companyId, $userRole, $username, $fullName, $ipAddress = null, $userAgent = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_activity_logs 
                (user_id, company_id, user_role, username, full_name, event_type, login_time, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, 'login', NOW(), ?, ?)
            ");
            
            return $stmt->execute([
                $userId,
                $companyId,
                $userRole,
                $username,
                $fullName,
                $ipAddress,
                $userAgent
            ]);
        } catch (\Exception $e) {
            error_log("UserActivityLog::logLogin error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log user logout and calculate session duration
     */
    public function logLogout($userId, $sessionStartTime = null) {
        try {
            // Get the most recent login for this user that doesn't have a logout yet
            $loginStmt = $this->db->prepare("
                SELECT id, login_time, created_at
                FROM user_activity_logs
                WHERE user_id = ? 
                AND event_type = 'login'
                AND logout_time IS NULL
                ORDER BY login_time DESC
                LIMIT 1
            ");
            $loginStmt->execute([$userId]);
            $loginRecord = $loginStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($loginRecord) {
                $loginTime = strtotime($loginRecord['login_time'] ?? $loginRecord['created_at']);
                $logoutTime = time();
                $sessionDuration = $logoutTime - $loginTime;
                
                $stmt = $this->db->prepare("
                    UPDATE user_activity_logs
                    SET event_type = 'logout',
                        logout_time = NOW(),
                        session_duration_seconds = ?
                    WHERE id = ?
                ");
                
                return $stmt->execute([$sessionDuration, $loginRecord['id']]);
            } else {
                // If no login record found, create a logout-only record
                $userStmt = $this->db->prepare("
                    SELECT id, company_id, role, username, full_name
                    FROM users
                    WHERE id = ?
                ");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $stmt = $this->db->prepare("
                        INSERT INTO user_activity_logs 
                        (user_id, company_id, user_role, username, full_name, event_type, logout_time, ip_address)
                        VALUES (?, ?, ?, ?, ?, 'logout', NOW(), ?)
                    ");
                    
                    return $stmt->execute([
                        $userId,
                        $user['company_id'],
                        $user['role'],
                        $user['username'],
                        $user['full_name'],
                        $_SERVER['REMOTE_ADDR'] ?? null
                    ]);
                }
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("UserActivityLog::logLogout error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all activity logs with filters
     */
    public function getAll($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = "ual.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['company_id'])) {
            $where[] = "ual.company_id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['user_role'])) {
            $where[] = "ual.user_role = ?";
            $params[] = $filters['user_role'];
        }
        
        if (!empty($filters['event_type'])) {
            $where[] = "ual.event_type = ?";
            $params[] = $filters['event_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(ual.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(ual.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        
        $sql = "
            SELECT 
                ual.*,
                c.name as company_name
            FROM user_activity_logs ual
            LEFT JOIN companies c ON ual.company_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ual.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get activity statistics
     */
    public function getStatistics($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['company_id'])) {
            $where[] = "ual.company_id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(ual.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(ual.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql = "
            SELECT 
                COUNT(*) as total_logs,
                COUNT(DISTINCT ual.user_id) as unique_users,
                SUM(CASE WHEN ual.event_type = 'login' THEN 1 ELSE 0 END) as total_logins,
                SUM(CASE WHEN ual.event_type = 'logout' THEN 1 ELSE 0 END) as total_logouts,
                AVG(ual.session_duration_seconds) as avg_session_duration,
                SUM(ual.session_duration_seconds) as total_session_time
            FROM user_activity_logs ual
            WHERE " . implode(' AND ', $where) . "
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get activity logs by user
     */
    public function getByUser($userId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT 
                ual.*,
                c.name as company_name
            FROM user_activity_logs ual
            LEFT JOIN companies c ON ual.company_id = c.id
            WHERE ual.user_id = ?
            ORDER BY ual.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

