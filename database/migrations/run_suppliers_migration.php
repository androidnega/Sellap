<?php
/**
 * Run Suppliers System Migration
 * 
 * This script creates all tables needed for the supplier management system:
 * - suppliers
 * - purchase_orders
 * - purchase_order_items
 * - supplier_products
 * - purchase_payments
 * 
 * Usage: php database/migrations/run_suppliers_migration.php
 * Or visit: http://localhost/sellapp/database/migrations/run_suppliers_migration.php
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
    echo "<html><head><title>Run Suppliers Migration</title><style>
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
    
    echo php_sapi_name() === 'cli' ? "=== Running Suppliers System Migration ===\n\n" : "<h1>Running Suppliers System Migration</h1>";
    
    // Read SQL file
    $sqlFile = __DIR__ . '/create_suppliers_system.sql';
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
            return !empty($stmt);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Execute each statement
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        // Add semicolon if missing
        if (substr($statement, -1) !== ';') {
            $statement .= ';';
        }
        
        try {
            $db->exec($statement);
            $successCount++;
            
            // Extract table name for logging
            if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];
                echo php_sapi_name() === 'cli' 
                    ? "✓ Created table: {$tableName}\n" 
                    : "<div class='success'>✓ Created table: <strong>{$tableName}</strong></div>";
            } elseif (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?(\w+)/i', $statement, $matches)) {
                $tableName = $matches[1];
                echo php_sapi_name() === 'cli' 
                    ? "✓ Created table: {$tableName}\n" 
                    : "<div class='success'>✓ Created table: <strong>{$tableName}</strong></div>";
            }
        } catch (\PDOException $e) {
            $errorMsg = $e->getMessage();
            
            // Check for "already exists" errors (these are okay)
            if (stripos($errorMsg, 'already exists') !== false || 
                stripos($errorMsg, 'Duplicate') !== false ||
                (stripos($errorMsg, 'Table') !== false && stripos($errorMsg, 'already') !== false) ||
                (stripos($errorMsg, 'Duplicate key') !== false) ||
                (stripos($errorMsg, 'Duplicate entry') !== false)) {
                // This is okay, table/index already exists
                $successCount++;
                if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $statement, $matches) ||
                    preg_match('/CREATE TABLE (?:IF NOT EXISTS )?(\w+)/i', $statement, $matches)) {
                    $tableName = $matches[1];
                    echo php_sapi_name() === 'cli' 
                        ? "ℹ Table already exists: {$tableName}\n" 
                        : "<div class='info'>ℹ Table already exists: <strong>{$tableName}</strong></div>";
                }
            } else {
                // Real error
                $errorCount++;
                $errors[] = [
                    'statement' => substr($statement, 0, 100) . '...',
                    'error' => $errorMsg
                ];
                echo php_sapi_name() === 'cli' 
                    ? "✗ Error: {$errorMsg}\n" 
                    : "<div class='error'>✗ Error: " . htmlspecialchars($errorMsg) . "</div>";
            }
        }
    }
    
    echo php_sapi_name() === 'cli' ? "\n" : "<br>";
    echo php_sapi_name() === 'cli' 
        ? "=== Migration Complete ===\n" 
        : "<h2>Migration Complete</h2>";
    echo php_sapi_name() === 'cli' 
        ? "Successfully executed: {$successCount} statement(s)\n" 
        : "<div class='info'>Successfully executed: <strong>{$successCount}</strong> statement(s)</div>";
    
    if ($errorCount > 0) {
        echo php_sapi_name() === 'cli' 
            ? "Errors: {$errorCount} statement(s)\n" 
            : "<div class='error'>Errors: <strong>{$errorCount}</strong> statement(s)</div>";
        
        if (php_sapi_name() !== 'cli') {
            echo "<h3>Error Details:</h3><pre>";
            foreach ($errors as $error) {
                echo htmlspecialchars($error['error']) . "\n";
            }
            echo "</pre>";
        }
    }
    
    if ($successCount > 0 && $errorCount === 0) {
        echo php_sapi_name() === 'cli' 
            ? "\n✓ All tables created successfully!\n" 
            : "<div class='success'><strong>✓ All tables created successfully!</strong><br><br>You can now access the Suppliers and Purchase Orders features from the sidebar.</div>";
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

