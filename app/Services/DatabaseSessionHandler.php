<?php

namespace App\Services;

/**
 * Database Session Handler
 * Stores PHP sessions in database instead of file system
 * Prevents disk space usage on server
 */
class DatabaseSessionHandler implements \SessionHandlerInterface {
    
    private $db;
    private $tableName = 'sessions';
    
    public function __construct() {
        // Check if Database class is available
        if (!class_exists('Database')) {
            throw new \RuntimeException('Database class not available. Ensure config/database.php is loaded before using DatabaseSessionHandler.');
        }
        
        $this->db = \Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }
    
    /**
     * Create sessions table if it doesn't exist
     */
    private function createTableIfNotExists() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS {$this->tableName} (
                    id VARCHAR(128) PRIMARY KEY,
                    data TEXT,
                    last_activity INT UNSIGNED NOT NULL,
                    INDEX idx_last_activity (last_activity)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Exception $e) {
            // Table might already exist, ignore error
        }
    }
    
    public function open($savePath, $sessionName) {
        return true;
    }
    
    public function close() {
        return true;
    }
    
    public function read($sessionId) {
        try {
            $stmt = $this->db->prepare("SELECT data FROM {$this->tableName} WHERE id = ?");
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['data'];
            }
        } catch (\Exception $e) {
            // Log error but don't break session
            CloudinaryStorage::logError("Session read error: " . $e->getMessage());
        }
        
        return '';
    }
    
    public function write($sessionId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->tableName} (id, data, last_activity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    data = VALUES(data),
                    last_activity = VALUES(last_activity)
            ");
            
            return $stmt->execute([$sessionId, $data, time()]);
        } catch (\Exception $e) {
            // Don't use CloudinaryStorage here to avoid circular dependency
            return false;
        }
    }
    
    public function destroy($sessionId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->tableName} WHERE id = ?");
            return $stmt->execute([$sessionId]);
        } catch (\Exception $e) {
            // Don't use CloudinaryStorage here to avoid circular dependency
            return false;
        }
    }
    
    public function gc($maxLifetime) {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->tableName} WHERE last_activity < ?");
            $oldest = time() - $maxLifetime;
            return $stmt->execute([$oldest]);
        } catch (\Exception $e) {
            // Don't use CloudinaryStorage here to avoid circular dependency
            return false;
        }
    }
}

