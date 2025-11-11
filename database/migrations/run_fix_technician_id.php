<?php
/**
 * Fix technician_id data type mismatch in repairs_new table
 * 
 * Usage: php database/migrations/run_fix_technician_id.php
 * Or visit: http://localhost/sellapp/database/migrations/run_fix_technician_id.php
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
    echo "<html><head><title>Fix Technician ID Type</title><style>
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
    
    echo php_sapi_name() === 'cli' ? "Fixing technician_id data type...\n\n" : "<h1>Fix Technician ID Data Type</h1>";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/fix_repairs_technician_id_type.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            $stmt = trim($stmt);
            return !empty($stmt) && 
                   (stripos($stmt, 'ALTER TABLE') !== false || 
                    stripos($stmt, 'DROP FOREIGN KEY') !== false ||
                    stripos($stmt, 'ADD CONSTRAINT') !== false);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        // Extract operation type for logging
        $operation = '';
        if (stripos($statement, 'DROP FOREIGN KEY') !== false) {
            $operation = 'Dropping foreign key constraint';
        } elseif (stripos($statement, 'MODIFY COLUMN') !== false) {
            $operation = 'Modifying technician_id column';
        } elseif (stripos($statement, 'ADD CONSTRAINT') !== false) {
            $operation = 'Adding foreign key constraint';
        } else {
            $operation = 'Executing SQL statement';
        }
        
        try {
            $db->exec($statement);
            $successCount++;
            $msg = "✓ {$operation} - Success";
            echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='success'>$msg</div>";
            
        } catch (\PDOException $e) {
            $errorCount++;
            $errorMsg = "✗ {$operation} - Error: " . $e->getMessage();
            
            // Check if constraint doesn't exist (that's okay for DROP)
            if (stripos($statement, 'DROP FOREIGN KEY') !== false && 
                (strpos($e->getMessage(), "doesn't exist") !== false ||
                 strpos($e->getMessage(), "Unknown key") !== false)) {
                $msg = "ℹ {$operation} - Constraint doesn't exist (skipping)";
                echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='info'>$msg</div>";
                $errorCount--;
                $successCount++;
            } else {
                echo php_sapi_name() === 'cli' ? "$errorMsg\n" : "<div class='error'>$errorMsg</div>";
            }
        }
    }
    
    // Verify the fix
    echo php_sapi_name() === 'cli' ? "\nVerifying fix...\n" : "<h2>Verification</h2>";
    
    try {
        $stmt = $db->query("SHOW COLUMNS FROM repairs_new WHERE Field = 'technician_id'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($column) {
            $dataType = $column['Type'];
            if (stripos($dataType, 'bigint') !== false && stripos($dataType, 'unsigned') !== false) {
                $msg = "✓ technician_id column is now BIGINT UNSIGNED - Fix successful!";
                echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='success'>$msg</div>";
            } else {
                $msg = "⚠ technician_id column type is: {$dataType} (expected BIGINT UNSIGNED)";
                echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='error'>$msg</div>";
            }
        } else {
            $msg = "✗ Could not find technician_id column";
            echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='error'>$msg</div>";
        }
    } catch (\PDOException $e) {
        $msg = "✗ Error verifying fix: " . $e->getMessage();
        echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='error'>$msg</div>";
    }
    
    // Summary
    echo php_sapi_name() === 'cli' ? "\n" : "<h2>Summary</h2>";
    $summary = "Migration completed: {$successCount} operations successful";
    if ($errorCount > 0) {
        $summary .= ", {$errorCount} errors";
    }
    
    $msg = "✓ $summary";
    echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='success'>$msg</div>";
    
    if (php_sapi_name() !== 'cli') {
        echo "<p><a href='javascript:location.reload()'>Run Again</a> | <a href='" . (defined('BASE_URL_PATH') ? BASE_URL_PATH : '') . "/dashboard/repairs/create'>Test Repair Creation</a></p>";
        echo "</div></body></html>";
    }
    
    exit($errorCount > 0 ? 1 : 0);
    
} catch (\Exception $e) {
    $errorMsg = "Fatal error: " . $e->getMessage();
    echo php_sapi_name() === 'cli' ? "$errorMsg\n" : "<div class='error'>$errorMsg</div>";
    
    if (php_sapi_name() !== 'cli') {
        echo "</div></body></html>";
    }
    
    exit(1);
}

