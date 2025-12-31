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
        // Get environment (local or production)
        $appEnv = getenv('APP_ENV') ?: 'local';
        
        // Load database credentials from environment variables
        // Falls back to defaults if not set
        if ($appEnv === 'local' || $appEnv === 'development') {
            // Localhost/Development database credentials
            $host = getenv('DB_HOST') ?: '127.0.0.1:3307';
            $dbname = getenv('DB_NAME') ?: 'sellapp_db';
            $username = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASS') ?: 'newpassword';
        } else {
            // Production/Live server database credentials
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'manuelc8_sellapp';
            $username = getenv('DB_USER') ?: 'manuelc8_sellapp';
            $password = getenv('DB_PASS') ?: 'Atomic2@2020^';
        }
        
        // Debug output (only in development)
        if ($appEnv === 'local' || $appEnv === 'development') {
            error_log("Database connection attempt: env=$appEnv, host=$host, dbname=$dbname, username=$username");
        }

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
            // Log detailed error for debugging
            error_log("PDO Exception: " . $e->getMessage());
            error_log("Connection string: mysql:host=$host;dbname=$dbname;charset=utf8mb4");
            throw new \Exception("Database connection failed: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
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

