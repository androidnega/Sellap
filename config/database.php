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
        // Auto-detect environment based on domain if not explicitly set
        $appEnv = getenv('APP_ENV');
        
        // If APP_ENV is not set, auto-detect from HTTP_HOST
        if (empty($appEnv) && isset($_SERVER['HTTP_HOST'])) {
            $httpHost = $_SERVER['HTTP_HOST'];
            // Check if we're on app.dcapple.com
            if (preg_match('#app\.dcapple\.com#', $httpHost)) {
                $appEnv = 'dcapple';
            }
            // Check if we're on the live server domain (sellapp.store)
            elseif (preg_match('#sellapp\.store#', $httpHost) || 
                preg_match('#www\.sellapp\.store#', $httpHost)) {
                $appEnv = 'production';
            } else {
                $appEnv = 'local';
            }
        }
        
        // Default to local if still not set
        if (empty($appEnv)) {
            $appEnv = 'local';
        }
        
        // Load database credentials from environment variables
        // Falls back to defaults if not set
        if ($appEnv === 'dcapple') {
            // app.dcapple.com database credentials
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'dcapple3_app';
            $username = getenv('DB_USER') ?: 'dcapple3_appuser';
            $password = getenv('DB_PASS') ?: 'Atomic2@2020^';
        } elseif ($appEnv === 'local' || $appEnv === 'development') {
            // Localhost/Development database credentials
            $host = getenv('DB_HOST') ?: '127.0.0.1:3307';
            $dbname = getenv('DB_NAME') ?: 'sellapp_db';
            $username = getenv('DB_USER') ?: 'root';
            $password = getenv('DB_PASS') ?: 'newpassword';
        } else {
            // Production/Live server database credentials (sellapp.store)
            $host = getenv('DB_HOST') ?: 'localhost';
            
            $dbname = getenv('DB_NAME') ?: 'manuelc8_sellapp';
            $username = getenv('DB_USER') ?: 'manuelc8_sellapp';
            $password = getenv('DB_PASS') ?: 'Atomic2@2020^';
        }
        
        // Debug output (only in development)
        if ($appEnv === 'local' || $appEnv === 'development') {
            error_log("Database connection attempt: env=$appEnv, host=$host, dbname=$dbname, username=$username");
        } else {
            // Log environment detection for production debugging (without sensitive data)
            error_log("Database connection attempt: env=$appEnv, host=$host, dbname=$dbname");
        }

        try {
            // Set connection timeout (5 seconds)
            $options = [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ];
            
            // Parse host and port if port is specified
            $hostParts = explode(':', $host);
            $dbHost = $hostParts[0];
            $dbPort = isset($hostParts[1]) ? $hostParts[1] : 3306;
            
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbname;charset=utf8mb4";
            
            $this->connection = new PDO($dsn, $username, $password, $options);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Test the connection with a simple query
            $this->connection->query("SELECT 1");
            
        } catch (PDOException $e) {
            // Log detailed error for debugging
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            error_log("PDO Exception [$errorCode]: " . $errorMessage);
            error_log("Connection attempt: mysql:host=$host;dbname=$dbname");
            
            // Provide more specific error messages
            if (strpos($errorMessage, 'Access denied') !== false) {
                throw new \Exception("Database connection failed: Invalid credentials. Please check your database username and password.");
            } elseif (strpos($errorMessage, 'Unknown database') !== false) {
                throw new \Exception("Database connection failed: Database '$dbname' does not exist. Please create the database first.");
            } elseif (strpos($errorMessage, 'Connection refused') !== false || strpos($errorMessage, 'Connection timed out') !== false) {
                throw new \Exception("Database connection failed: Cannot connect to database server. Please check if MySQL is running and the host/port is correct.");
            } else {
                throw new \Exception("Database connection failed: " . $errorMessage);
            }
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

