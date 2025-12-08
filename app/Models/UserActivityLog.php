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
        $this->ensureTableExists();
    }

    /**
     * Ensure the user_activity_logs table exists
     */
    private function ensureTableExists() {
        try {
            // Check if table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'user_activity_logs'");
            if ($checkTable->rowCount() == 0) {
                // Create the table
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS user_activity_logs (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        user_id BIGINT UNSIGNED NOT NULL,
                        company_id BIGINT UNSIGNED,
                        user_role VARCHAR(50) NOT NULL,
                        username VARCHAR(255) NOT NULL,
                        full_name VARCHAR(255),
                        event_type ENUM('login', 'logout', 'session_timeout') NOT NULL,
                        login_time TIMESTAMP NULL,
                        logout_time TIMESTAMP NULL,
                        session_duration_seconds INT DEFAULT 0,
                        ip_address VARCHAR(45),
                        user_agent TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user (user_id),
                        INDEX idx_company (company_id),
                        INDEX idx_role (user_role),
                        INDEX idx_event_type (event_type),
                        INDEX idx_login_time (login_time),
                        INDEX idx_created_at (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                
                // Add foreign keys if tables exist
                try {
                    $this->db->exec("
                        ALTER TABLE user_activity_logs
                        ADD CONSTRAINT fk_user_activity_user
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    ");
                } catch (\Exception $e) {
                    // Foreign key might already exist or users table might not exist yet
                    error_log("Could not add user foreign key: " . $e->getMessage());
                }
                
                try {
                    $this->db->exec("
                        ALTER TABLE user_activity_logs
                        ADD CONSTRAINT fk_user_activity_company
                        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
                    ");
                } catch (\Exception $e) {
                    // Foreign key might already exist or companies table might not exist yet
                    error_log("Could not add company foreign key: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log("UserActivityLog::ensureTableExists error: " . $e->getMessage());
            // Don't throw - let the application continue
        }
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
            // Check both event_type = 'login' OR (event_type = 'logout' but logout_time is NULL - shouldn't happen but handle it)
            $loginStmt = $this->db->prepare("
                SELECT id, login_time, created_at, event_type
                FROM user_activity_logs
                WHERE user_id = ? 
                AND (event_type = 'login' OR event_type = 'logout')
                AND (logout_time IS NULL OR logout_time = '')
                ORDER BY login_time DESC, created_at DESC
                LIMIT 1
            ");
            $loginStmt->execute([$userId]);
            $loginRecord = $loginStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($loginRecord) {
                // Calculate session duration
                $loginTime = $loginRecord['login_time'] ? strtotime($loginRecord['login_time']) : strtotime($loginRecord['created_at']);
                if ($sessionStartTime && is_numeric($sessionStartTime)) {
                    $loginTime = $sessionStartTime; // Use session start time if provided
                }
                $logoutTime = time();
                $sessionDuration = max(0, $logoutTime - $loginTime); // Ensure non-negative
                
                // Update the login record with logout information
                // Keep event_type as 'login' but add logout_time and duration
                $stmt = $this->db->prepare("
                    UPDATE user_activity_logs
                    SET logout_time = NOW(),
                        session_duration_seconds = ?
                    WHERE id = ?
                ");
                
                $result = $stmt->execute([$sessionDuration, $loginRecord['id']]);
                
                if ($result && $stmt->rowCount() > 0) {
                    error_log("UserActivityLog::logLogout - Updated login record {$loginRecord['id']} with logout time. Duration: {$sessionDuration}s");
                } else {
                    error_log("UserActivityLog::logLogout - Failed to update login record {$loginRecord['id']}. Rows affected: " . $stmt->rowCount());
                }
                
                // Also create a separate logout event record for tracking
                $userStmt = $this->db->prepare("
                    SELECT company_id, role, username, full_name
                    FROM users
                    WHERE id = ?
                ");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    try {
                        $logoutStmt = $this->db->prepare("
                            INSERT INTO user_activity_logs 
                            (user_id, company_id, user_role, username, full_name, event_type, login_time, logout_time, session_duration_seconds, ip_address)
                            VALUES (?, ?, ?, ?, ?, 'logout', ?, NOW(), ?, ?)
                        ");
                        $logoutStmt->execute([
                            $userId,
                            $user['company_id'],
                            $user['role'],
                            $user['username'],
                            $user['full_name'],
                            $loginRecord['login_time'] ?? date('Y-m-d H:i:s', $loginTime),
                            $sessionDuration,
                            $_SERVER['REMOTE_ADDR'] ?? null
                        ]);
                        error_log("UserActivityLog::logLogout - Created separate logout event record for user {$userId}");
                    } catch (\Exception $e) {
                        // Don't fail if we can't create the separate logout record
                        error_log("UserActivityLog::logLogout - Could not create separate logout record: " . $e->getMessage());
                    }
                }
                
                return $result;
            } else {
                // If no login record found, create a logout-only record
                error_log("UserActivityLog::logLogout - No login record found for user {$userId}, creating logout-only record");
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
            error_log("UserActivityLog::logLogout trace: " . $e->getTraceAsString());
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
        
        // LIMIT and OFFSET must be integers, not bound parameters
        $limit = intval($filters['limit'] ?? 100);
        $offset = intval($filters['offset'] ?? 0);
        
        $sql = "
            SELECT 
                ual.*,
                c.name as company_name
            FROM user_activity_logs ual
            LEFT JOIN companies c ON ual.company_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ual.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total count of activity logs matching filters
     */
    public function getCount($filters = []) {
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
        
        $sql = "
            SELECT COUNT(*) as total
            FROM user_activity_logs ual
            WHERE " . implode(' AND ', $where) . "
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
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

