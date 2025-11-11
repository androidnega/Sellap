<?php
/**
 * Automatic Migration Runner
 * Run this script to create the required tables for the reset system
 * 
 * Usage: php database/migrations/run_migration.php
 * Or visit: http://localhost/sellapp/database/migrations/run_migration.php
 */

require_once __DIR__ . '/../../config/database.php';

// Only allow CLI or localhost access
if (php_sapi_name() !== 'cli') {
    $allowed = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost']) || 
               (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);
    
    if (!$allowed) {
        die("This script can only be run from localhost or command line.");
    }
    
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><title>Migration Runner</title><style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style></head><body><div class='container'>";
}

try {
    $db = \Database::getInstance()->getConnection();
    
    echo php_sapi_name() === 'cli' ? "Starting migration...\n" : "<h1>Database Migration Runner</h1>";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/create_reset_system_tables.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove SQL comments (-- and /* */)
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove -- comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove /* */ comments
    
    // Split by semicolon and filter
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            $stmt = trim($stmt);
            return !empty($stmt) && 
                   strpos(strtoupper($stmt), 'CREATE TABLE') !== false;
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Execute each CREATE TABLE statement
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        // Extract table name for logging
        $tableName = '';
        if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?/i', $statement, $matches)) {
            $tableName = $matches[1];
        }
        
        try {
            // Add semicolon back
            if (substr($statement, -1) !== ';') {
                $statement .= ';';
            }
            
            $db->exec($statement);
            
            $successCount++;
            $msg = "✓ Table '{$tableName}' created successfully";
            echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='success'>$msg</div>";
            
        } catch (\PDOException $e) {
            $errorCount++;
            $errorMsg = "✗ Error creating table '{$tableName}': " . $e->getMessage();
            $errors[] = $errorMsg;
            
            // Check if table already exists (that's okay)
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate table') !== false ||
                strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'already') !== false) {
                $msg = "ℹ Table '{$tableName}' already exists (skipping)";
                echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='info'>$msg</div>";
                $errorCount--; // Don't count as error
                $successCount++; // Count as success since table exists
            } else {
                echo php_sapi_name() === 'cli' ? "$errorMsg\n" : "<div class='error'>$errorMsg</div>";
            }
        }
    }
    
    // Verify tables were created
    echo php_sapi_name() === 'cli' ? "\nVerifying tables...\n" : "<h2>Verification</h2>";
    
    $requiredTables = ['admin_actions', 'reset_jobs'];
    $createdTables = [];
    
    foreach ($requiredTables as $table) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
            if ($stmt->rowCount() > 0) {
                $createdTables[] = $table;
                $msg = "✓ Table '{$table}' exists";
                echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='success'>$msg</div>";
            } else {
                $msg = "✗ Table '{$table}' not found";
                echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='error'>$msg</div>";
            }
        } catch (\PDOException $e) {
            $msg = "✗ Error checking table '{$table}': " . $e->getMessage();
            echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='error'>$msg</div>";
        }
    }
    
    // Summary
    echo php_sapi_name() === 'cli' ? "\n" : "<h2>Summary</h2>";
    $summary = "Migration completed: {$successCount} tables processed, " . count($createdTables) . "/" . count($requiredTables) . " required tables exist";
    
    if (count($createdTables) === count($requiredTables)) {
        $msg = "✓ $summary - All tables created successfully!";
        echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='success'>$msg</div>";
    } else {
        $msg = "⚠ $summary - Some tables may be missing";
        echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='error'>$msg</div>";
    }
    
    if (php_sapi_name() !== 'cli') {
        echo "<p><a href='javascript:location.reload()'>Run Migration Again</a> | <a href='" . (defined('BASE_URL_PATH') ? BASE_URL_PATH : '') . "/dashboard/companies'>Go to Dashboard</a></p>";
        echo "</div></body></html>";
    }
    
    exit(count($createdTables) === count($requiredTables) ? 0 : 1);
    
} catch (\Exception $e) {
    $errorMsg = "Fatal error: " . $e->getMessage();
    echo php_sapi_name() === 'cli' ? "$errorMsg\n" : "<div class='error'>$errorMsg</div>";
    
    if (php_sapi_name() !== 'cli') {
        echo "</div></body></html>";
    }
    
    exit(1);
}

