<?php

/**
 * Database Configuration
 * Singleton pattern for PDO connection
 */

if (!class_exists('Database')) {
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        // Use hardcoded values since we know they work
        $host = '127.0.0.1:3306';
        $dbname = 'sellapp_db';
        $username = 'root';
        $password = 'newpassword';
        
        // Debug output (remove in production)
        error_log("Database connection attempt: host=$host, dbname=$dbname, username=$username");

        try {
            $this->connection = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            try {
                self::$instance = new Database();
            } catch (Exception $e) {
                // Reset instance on failure to allow retry
                self::$instance = null;
                throw $e;
            }
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }
}
} // End class_exists check

