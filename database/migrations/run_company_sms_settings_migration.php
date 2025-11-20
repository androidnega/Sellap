<?php
/**
 * Run Company SMS Notification Settings Migration
 * 
 * This script adds SMS notification preference columns to company_sms_accounts table
 * 
 * Usage: php database/migrations/run_company_sms_settings_migration.php
 * Or visit: http://localhost/sellapp/database/migrations/run_company_sms_settings_migration.php
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
    echo "<html><head><title>Company SMS Settings Migration</title><style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style></head><body><div class='container'>";
}

try {
    $db = \Database::getInstance()->getConnection();
    
    echo php_sapi_name() === 'cli' ? "=== Running Company SMS Settings Migration ===\n\n" : "<h1>Company SMS Settings Migration</h1>";
    
    // Check if table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'company_sms_accounts'");
    if (!$tableCheck || $tableCheck->rowCount() == 0) {
        $message = "Table 'company_sms_accounts' does not exist. Please create it first.";
        echo php_sapi_name() === 'cli' ? "ERROR: $message\n" : "<div class='error'>$message</div>";
        exit(1);
    }
    
    echo php_sapi_name() === 'cli' ? "✓ Table 'company_sms_accounts' exists\n" : "<div class='success'>✓ Table 'company_sms_accounts' exists</div>";
    
    // Read and execute migration SQL
    $migrationFile = __DIR__ . '/add_company_sms_notification_settings.sql';
    if (!file_exists($migrationFile)) {
        $message = "Migration file not found: $migrationFile";
        echo php_sapi_name() === 'cli' ? "ERROR: $message\n" : "<div class='error'>$message</div>";
        exit(1);
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Split SQL into individual statements
    // Remove comments
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Split by semicolon but keep PREPARE/EXECUTE blocks together
    $statements = [];
    $currentStatement = '';
    $inPrepareBlock = false;
    
    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) continue;
        
        $currentStatement .= $line . "\n";
        
        // Check if we're in a PREPARE block
        if (preg_match('/PREPARE\s+stmt\s+FROM/i', $trimmed)) {
            $inPrepareBlock = true;
        }
        
        // Check if we're ending a PREPARE block
        if (preg_match('/DEALLOCATE\s+PREPARE\s+stmt/i', $trimmed)) {
            $inPrepareBlock = false;
            $statements[] = trim($currentStatement);
            $currentStatement = '';
        } elseif (!$inPrepareBlock && substr($trimmed, -1) === ';') {
            // Regular statement ending with semicolon
            $statements[] = trim($currentStatement);
            $currentStatement = '';
        }
    }
    
    // Add any remaining statement
    if (!empty(trim($currentStatement))) {
        $statements[] = trim($currentStatement);
    }
    
    // Filter out empty statements
    $statements = array_filter($statements, function($stmt) {
        return !empty(trim($stmt));
    });
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Execute each statement
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $db->exec($statement);
            $successCount++;
            echo php_sapi_name() === 'cli' ? "✓ Executed statement " . ($index + 1) . "\n" : "<div class='success'>✓ Executed statement " . ($index + 1) . "</div>";
        } catch (\PDOException $e) {
            // Check if it's a "column already exists" or "duplicate" error
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'already exists') !== false || 
                strpos($errorMsg, 'Duplicate column') !== false ||
                strpos($errorMsg, 'Column') !== false && strpos($errorMsg, 'already exists') !== false) {
                echo php_sapi_name() === 'cli' ? "⚠ Statement " . ($index + 1) . ": Column already exists (skipped)\n" : "<div class='warning'>⚠ Statement " . ($index + 1) . ": Column already exists (skipped)</div>";
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Statement " . ($index + 1) . ": " . $errorMsg;
                echo php_sapi_name() === 'cli' ? "✗ Error in statement " . ($index + 1) . ": " . $errorMsg . "\n" : "<div class='error'>✗ Error in statement " . ($index + 1) . ": " . htmlspecialchars($errorMsg) . "</div>";
            }
        }
    }
    
    // Summary
    echo php_sapi_name() === 'cli' ? "\n=== Migration Summary ===\n" : "<h2>Migration Summary</h2>";
    echo php_sapi_name() === 'cli' ? "Success: $successCount\n" : "<div class='info'>Success: $successCount</div>";
    echo php_sapi_name() === 'cli' ? "Errors: $errorCount\n" : "<div class='info'>Errors: $errorCount</div>";
    
    if ($errorCount > 0 && !empty($errors)) {
        echo php_sapi_name() === 'cli' ? "\nErrors:\n" . implode("\n", $errors) . "\n" : "<div class='error'><strong>Errors:</strong><pre>" . htmlspecialchars(implode("\n", $errors)) . "</pre></div>";
    }
    
    if ($errorCount == 0) {
        echo php_sapi_name() === 'cli' ? "\n✓ Migration completed successfully!\n" : "<div class='success'><strong>✓ Migration completed successfully!</strong></div>";
    } else {
        echo php_sapi_name() === 'cli' ? "\n⚠ Migration completed with some errors. Please review above.\n" : "<div class='warning'><strong>⚠ Migration completed with some errors. Please review above.</strong></div>";
    }
    
    // Verify columns were added
    echo php_sapi_name() === 'cli' ? "\n=== Verifying Columns ===\n" : "<h2>Verifying Columns</h2>";
    $columns = ['sms_purchase_enabled', 'sms_repair_enabled', 'sms_swap_enabled'];
    foreach ($columns as $column) {
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'company_sms_accounts' 
            AND COLUMN_NAME = '$column'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['count'] > 0) {
            echo php_sapi_name() === 'cli' ? "✓ Column '$column' exists\n" : "<div class='success'>✓ Column '$column' exists</div>";
        } else {
            echo php_sapi_name() === 'cli' ? "✗ Column '$column' does not exist\n" : "<div class='error'>✗ Column '$column' does not exist</div>";
        }
    }
    
} catch (\Exception $e) {
    $message = "Migration failed: " . $e->getMessage();
    echo php_sapi_name() === 'cli' ? "ERROR: $message\n" : "<div class='error'>$message</div>";
    echo php_sapi_name() === 'cli' ? "Stack trace:\n" . $e->getTraceAsString() . "\n" : "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    echo "</div></body></html>";
}

