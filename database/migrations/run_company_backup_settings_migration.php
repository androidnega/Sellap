<?php
/**
 * Run Company Backup Settings Migration
 * 
 * This script creates the company_backup_settings table needed for auto-backup configuration.
 * 
 * Usage: php database/migrations/run_company_backup_settings_migration.php
 * Or visit: https://sellapp.store/database/migrations/run_company_backup_settings_migration.php
 */

require_once __DIR__ . '/../../config/database.php';

// Only allow CLI or localhost access (or authenticated admin)
if (php_sapi_name() !== 'cli') {
    // Allow if accessed via web (for live server)
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><title>Run Company Backup Settings Migration</title><style>
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
    
    echo php_sapi_name() === 'cli' ? "=== Running Company Backup Settings Migration ===\n\n" : "<h1>Running Company Backup Settings Migration</h1>";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/create_company_backup_settings_table.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove SQL comments (-- and /* */)
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove -- comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove /* */ comments
    
    // Split by semicolon and filter empty statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            $stmt = trim($stmt);
            return !empty($stmt) && 
                   !preg_match('/^(USE|SET|DELIMITER)/i', $stmt); // Skip USE, SET, DELIMITER commands
        }
    );
    
    if (empty($statements)) {
        throw new Exception("No valid SQL statements found in migration file");
    }
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Execute each statement
    foreach ($statements as $index => $statement) {
        try {
            $db->exec($statement);
            $successCount++;
            echo php_sapi_name() === 'cli' 
                ? "✓ Statement " . ($index + 1) . " executed successfully\n" 
                : "<div class='success'>✓ Statement " . ($index + 1) . " executed successfully</div>";
        } catch (\PDOException $e) {
            $errorCount++;
            $errorMsg = "Statement " . ($index + 1) . " failed: " . $e->getMessage();
            $errors[] = $errorMsg;
            echo php_sapi_name() === 'cli' 
                ? "✗ $errorMsg\n" 
                : "<div class='error'>✗ " . htmlspecialchars($errorMsg) . "</div>";
            
            // If it's a "table already exists" error, that's okay
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo php_sapi_name() === 'cli' 
                    ? "  (This is okay - table already exists)\n" 
                    : "<div class='info'>  (This is okay - table already exists)</div>";
                $errorCount--; // Don't count as error
            }
        }
    }
    
    echo php_sapi_name() === 'cli' ? "\n" : "<br>";
    echo php_sapi_name() === 'cli' 
        ? "=== Migration Complete ===\n" 
        : "<h2>Migration Complete</h2>";
    echo php_sapi_name() === 'cli' 
        ? "Success: $successCount | Errors: $errorCount\n" 
        : "<div class='info'>Success: $successCount | Errors: $errorCount</div>";
    
    if ($errorCount > 0 && !empty($errors)) {
        echo php_sapi_name() === 'cli' ? "\nErrors:\n" : "<h3>Errors:</h3>";
        foreach ($errors as $error) {
            echo php_sapi_name() === 'cli' ? "  - $error\n" : "<div class='error'>" . htmlspecialchars($error) . "</div>";
        }
    }
    
    // Verify table was created
    try {
        $checkStmt = $db->query("SHOW TABLES LIKE 'company_backup_settings'");
        if ($checkStmt->rowCount() > 0) {
            echo php_sapi_name() === 'cli' 
                ? "\n✓ Table 'company_backup_settings' exists and is ready to use!\n" 
                : "<div class='success'><strong>✓ Table 'company_backup_settings' exists and is ready to use!</strong></div>";
        } else {
            echo php_sapi_name() === 'cli' 
                ? "\n⚠ Warning: Table 'company_backup_settings' was not found after migration.\n" 
                : "<div class='error'><strong>⚠ Warning: Table 'company_backup_settings' was not found after migration.</strong></div>";
        }
    } catch (\PDOException $e) {
        echo php_sapi_name() === 'cli' 
            ? "\n⚠ Could not verify table creation: " . $e->getMessage() . "\n" 
            : "<div class='error'>⚠ Could not verify table creation: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
} catch (\Exception $e) {
    echo php_sapi_name() === 'cli' 
        ? "Fatal Error: " . $e->getMessage() . "\n" 
        : "<div class='error'><strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    echo "</div></body></html>";
}

