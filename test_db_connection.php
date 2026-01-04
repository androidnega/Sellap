<?php

/**
 * Database Connection Test for app.dcapple.com
 * Standalone test file - does not affect existing database.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test - app.dcapple.com</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #667eea;
            background: #f9f9f9;
        }
        .success {
            color: #4caf50;
            font-weight: bold;
        }
        .error {
            color: #d32f2f;
            font-weight: bold;
        }
        .info {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .credentials {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Connection Test</h1>
        <p><strong>Server:</strong> app.dcapple.com</p>
        
        <?php
        // Database credentials for app.dcapple.com
        $host = 'localhost';
        $dbname = 'dcapple3_app';
        $username = 'dcapple3_appuser';
        $password = 'Atomic2@2020^';
        $port = 3306;
        
        echo '<div class="credentials">';
        echo '<strong>Database Configuration:</strong><br>';
        echo 'Host: ' . htmlspecialchars($host) . '<br>';
        echo 'Port: ' . htmlspecialchars($port) . '<br>';
        echo 'Database: ' . htmlspecialchars($dbname) . '<br>';
        echo 'Username: ' . htmlspecialchars($username) . '<br>';
        echo 'Password: ' . str_repeat('*', strlen($password)) . '<br>';
        echo '</div>';
        
        // Test 1: Check PHP PDO Extension
        echo '<div class="test-section">';
        echo '<h2>Test 1: PHP PDO Extension</h2>';
        if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
            echo '<span class="success">✓ PDO and PDO_MySQL extensions are loaded</span><br>';
            echo '<div class="info">PDO Version: ' . phpversion('pdo') . '<br>';
            echo 'PDO_MySQL Version: ' . phpversion('pdo_mysql') . '</div>';
        } else {
            echo '<span class="error">✗ PDO or PDO_MySQL extension is not loaded</span><br>';
            echo '<div class="info">Please enable PDO and PDO_MySQL extensions in your PHP configuration.</div>';
        }
        echo '</div>';
        
        // Test 2: Database Connection
        echo '<div class="test-section">';
        echo '<h2>Test 2: Database Connection</h2>';
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ];
            
            $connection = new PDO($dsn, $username, $password, $options);
            echo '<span class="success">✓ Database connection successful!</span><br>';
            echo '<div class="info">Connected to: ' . htmlspecialchars($dbname) . ' on ' . htmlspecialchars($host) . '</div>';
            
            // Test 3: Execute a simple query
            echo '<div class="test-section">';
            echo '<h2>Test 3: Execute Query</h2>';
            // Use backticks for reserved keywords or use different alias names
            $stmt = $connection->query("SELECT 1 as test_value, NOW() as server_time, DATABASE() as current_database");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<span class="success">✓ Query executed successfully</span><br>';
            echo '<div class="info">';
            echo 'Test Value: ' . htmlspecialchars($result['test_value']) . '<br>';
            echo 'Server Time: ' . htmlspecialchars($result['server_time']) . '<br>';
            echo 'Current Database: ' . htmlspecialchars($result['current_database']) . '<br>';
            echo '</div>';
            echo '</div>';
            
            // Test 4: List tables
            echo '<div class="test-section">';
            echo '<h2>Test 4: List Database Tables</h2>';
            $stmt = $connection->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($tables) > 0) {
                echo '<span class="success">✓ Found ' . count($tables) . ' table(s)</span><br>';
                echo '<div class="info">';
                echo '<strong>Tables:</strong><br>';
                echo '<ul>';
                foreach ($tables as $table) {
                    echo '<li>' . htmlspecialchars($table) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            } else {
                echo '<span class="error">⚠ No tables found in database</span><br>';
                echo '<div class="info">The database exists but contains no tables.</div>';
            }
            echo '</div>';
            
            // Test 5: Check MySQL version
            echo '<div class="test-section">';
            echo '<h2>Test 5: MySQL Server Information</h2>';
            $stmt = $connection->query("SELECT VERSION() as version, @@character_set_database as charset");
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<span class="success">✓ MySQL server information retrieved</span><br>';
            echo '<div class="info">';
            echo 'MySQL Version: ' . htmlspecialchars($info['version']) . '<br>';
            echo 'Database Charset: ' . htmlspecialchars($info['charset']) . '<br>';
            echo '</div>';
            echo '</div>';
            
            // Test 6: Test write permissions (if possible)
            echo '<div class="test-section">';
            echo '<h2>Test 6: Write Permissions Test</h2>';
            try {
                // Try to create a temporary table
                $connection->exec("CREATE TEMPORARY TABLE test_write_permissions (id INT PRIMARY KEY AUTO_INCREMENT, test_data VARCHAR(50))");
                $connection->exec("INSERT INTO test_write_permissions (test_data) VALUES ('test')");
                $stmt = $connection->query("SELECT COUNT(*) as count FROM test_write_permissions");
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($count['count'] > 0) {
                    echo '<span class="success">✓ Write permissions confirmed</span><br>';
                    echo '<div class="info">Successfully created temporary table and inserted test data.</div>';
                }
            } catch (PDOException $e) {
                echo '<span class="error">⚠ Write test failed</span><br>';
                echo '<div class="info">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            echo '</div>';
            
            $connection = null; // Close connection
            
        } catch (PDOException $e) {
            echo '<span class="error">✗ Database connection failed</span><br>';
            echo '<div class="info">';
            echo '<strong>Error Code:</strong> ' . htmlspecialchars($e->getCode()) . '<br>';
            echo '<strong>Error Message:</strong> ' . htmlspecialchars($e->getMessage()) . '<br><br>';
            
            // Provide troubleshooting tips
            echo '<strong>Troubleshooting Tips:</strong><br>';
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                echo '• Check if the username and password are correct<br>';
                echo '• Verify the user has permissions to access this database<br>';
            } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
                echo '• The database "' . htmlspecialchars($dbname) . '" does not exist<br>';
                echo '• Create the database or check the database name<br>';
            } elseif (strpos($e->getMessage(), 'Connection refused') !== false || strpos($e->getMessage(), 'Connection timed out') !== false) {
                echo '• MySQL server is not running or not accessible<br>';
                echo '• Check if the host and port are correct<br>';
                echo '• Verify firewall settings allow connections<br>';
            } else {
                echo '• Check MySQL server logs for more details<br>';
                echo '• Verify database credentials are correct<br>';
            }
            echo '</div>';
        } catch (Exception $e) {
            echo '<span class="error">✗ Unexpected error occurred</span><br>';
            echo '<div class="info">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        echo '</div>';
        
        // Test 7: PHP Environment Information
        echo '<div class="test-section">';
        echo '<h2>Test 7: PHP Environment Information</h2>';
        echo '<div class="info">';
        echo 'PHP Version: ' . phpversion() . '<br>';
        echo 'Server Software: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '<br>';
        echo 'Document Root: ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . '<br>';
        echo 'Script Filename: ' . ($_SERVER['SCRIPT_FILENAME'] ?? 'Unknown') . '<br>';
        echo 'Current Time: ' . date('Y-m-d H:i:s') . '<br>';
        echo '</div>';
        echo '</div>';
        ?>
        
        <div class="test-section" style="background: #e3f2fd; border-left-color: #2196f3;">
            <h2>📝 Notes</h2>
            <ul>
                <li>This is a test file and does not affect your existing database.php configuration</li>
                <li>Delete this file after testing for security reasons</li>
                <li>All database credentials are displayed for testing purposes only</li>
                <li>If all tests pass, your database connection is working correctly</li>
            </ul>
        </div>
    </div>
</body>
</html>

