<?php
/**
 * Automatic Migration Runner - Runs all SQL migrations in the migrations directory
 * 
 * Features:
 * - Discovers all .sql migration files automatically
 * - Tracks executed migrations to prevent duplicates
 * - Executes migrations in alphabetical order
 * - Handles errors gracefully and continues with next migration
 * - Supports both CLI and web access (localhost only)
 * 
 * Usage: php database/migrations/run_all_migrations.php
 * Or visit: http://localhost/sellapp/database/migrations/run_all_migrations.php
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
    echo "<html><head><title>Run All Migrations</title><style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border: 1px solid #b8daff; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .migration-item { padding: 8px; margin: 5px 0; border-left: 4px solid #ddd; background: #f8f9fa; }
        .migration-item.success { border-left-color: #28a745; }
        .migration-item.error { border-left-color: #dc3545; }
        .migration-item.skipped { border-left-color: #ffc107; }
    </style></head><body><div class='container'>";
}

try {
    $db = \Database::getInstance()->getConnection();
    
    echo php_sapi_name() === 'cli' ? "=== Running All Migrations ===\n\n" : "<h1>Running All Migrations</h1>";
    
    // Create migrations tracking table if it doesn't exist
    $createTrackingTable = "
        CREATE TABLE IF NOT EXISTS migrations_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration_file VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            execution_time_ms INT NULL,
            status ENUM('success', 'error', 'skipped') DEFAULT 'success',
            error_message TEXT NULL,
            INDEX idx_file (migration_file),
            INDEX idx_executed_at (executed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    try {
        $db->exec($createTrackingTable);
        echo php_sapi_name() === 'cli' ? "✓ Migrations tracking table ready\n\n" : "<div class='success'>✓ Migrations tracking table ready</div>";
    } catch (\PDOException $e) {
        echo php_sapi_name() === 'cli' ? "⚠ Could not create tracking table: " . $e->getMessage() . "\n\n" : "<div class='warning'>⚠ Could not create tracking table: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // Get all SQL migration files
    $migrationsDir = __DIR__;
    $migrationFiles = glob($migrationsDir . '/*.sql');
    
    // Filter out non-migration files (like run_migration.sql which is a template)
    $migrationFiles = array_filter($migrationFiles, function($file) {
        $basename = basename($file);
        // Skip template/example files
        return !in_array($basename, ['run_migration.sql']);
    });
    
    // Sort files alphabetically for consistent execution order
    sort($migrationFiles);
    
    if (empty($migrationFiles)) {
        echo php_sapi_name() === 'cli' ? "No migration files found.\n" : "<div class='info'>No migration files found.</div>";
        exit(0);
    }
    
    echo php_sapi_name() === 'cli' ? "Found " . count($migrationFiles) . " migration file(s)\n\n" : "<div class='info'>Found " . count($migrationFiles) . " migration file(s)</div>";
    
    // Get already executed migrations
    $executedMigrations = [];
    try {
        $stmt = $db->query("SELECT migration_file FROM migrations_log WHERE status = 'success'");
        $executedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (\PDOException $e) {
        // Table might not exist yet, continue
    }
    
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    $results = [];
    
    foreach ($migrationFiles as $migrationFile) {
        $filename = basename($migrationFile);
        $startTime = microtime(true);
        
        // Check if already executed
        if (in_array($filename, $executedMigrations)) {
            $skippedCount++;
            $msg = "⊘ Skipped: {$filename} (already executed)";
            echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='migration-item skipped'>$msg</div>";
            $results[] = ['file' => $filename, 'status' => 'skipped', 'message' => 'Already executed'];
            continue;
        }
        
        echo php_sapi_name() === 'cli' ? "Running: {$filename}... " : "<div class='migration-item'>Running: <strong>{$filename}</strong>... ";
        
        try {
            $sql = file_get_contents($migrationFile);
            
            if (empty(trim($sql))) {
                throw new Exception("Migration file is empty");
            }
            
            // Remove SQL comments (-- and /* */) but preserve structure
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
            
            // Execute each statement
            $executedStatements = 0;
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;
                
                // Add semicolon if missing
                if (substr($statement, -1) !== ';') {
                    $statement .= ';';
                }
                
                try {
                    $db->exec($statement);
                    $executedStatements++;
                } catch (\PDOException $e) {
                    // Check for "already exists" errors (these are okay)
                    $errorMsg = $e->getMessage();
                    if (stripos($errorMsg, 'already exists') !== false || 
                        stripos($errorMsg, 'Duplicate') !== false ||
                        (stripos($errorMsg, 'Table') !== false && stripos($errorMsg, 'already') !== false) ||
                        (stripos($errorMsg, 'Duplicate key') !== false) ||
                        (stripos($errorMsg, 'Duplicate entry') !== false)) {
                        // This is okay, continue
                        $executedStatements++;
                    } else {
                        // Real error, re-throw
                        throw $e;
                    }
                }
            }
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Log successful execution
            try {
                $stmt = $db->prepare("
                    INSERT INTO migrations_log (migration_file, execution_time_ms, status) 
                    VALUES (?, ?, 'success')
                    ON DUPLICATE KEY UPDATE 
                        executed_at = CURRENT_TIMESTAMP,
                        execution_time_ms = ?,
                        status = 'success',
                        error_message = NULL
                ");
                $stmt->execute([$filename, $executionTime, $executionTime]);
            } catch (\PDOException $e) {
                // Logging failed, but migration succeeded, so continue
            }
            
            $successCount++;
            $msg = "✓ Success: {$filename} ({$executedStatements} statement(s), {$executionTime}ms)";
            echo php_sapi_name() === 'cli' ? "$msg\n" : "$msg</div>";
            $results[] = ['file' => $filename, 'status' => 'success', 'statements' => $executedStatements, 'time' => $executionTime];
            
        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $errorMsg = $e->getMessage();
            
            // Log failed execution
            try {
                $stmt = $db->prepare("
                    INSERT INTO migrations_log (migration_file, execution_time_ms, status, error_message) 
                    VALUES (?, ?, 'error', ?)
                    ON DUPLICATE KEY UPDATE 
                        executed_at = CURRENT_TIMESTAMP,
                        execution_time_ms = ?,
                        status = 'error',
                        error_message = ?
                ");
                $stmt->execute([$filename, $executionTime, $errorMsg, $executionTime, $errorMsg]);
            } catch (\PDOException $logError) {
                // Logging failed, continue
            }
            
            $errorCount++;
            $msg = "✗ Error: {$filename} - " . $errorMsg;
            echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='migration-item error'>$msg</div>";
            $results[] = ['file' => $filename, 'status' => 'error', 'message' => $errorMsg];
        }
    }
    
    // Summary
    echo php_sapi_name() === 'cli' ? "\n=== Summary ===\n" : "<h2>Summary</h2>";
    $summary = "Total: " . count($migrationFiles) . " | ";
    $summary .= "✓ Success: {$successCount} | ";
    $summary .= "✗ Errors: {$errorCount} | ";
    $summary .= "⊘ Skipped: {$skippedCount}";
    
    if ($errorCount > 0) {
        $msg = "⚠ {$summary}";
        echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='warning'>$msg</div>";
    } else {
        $msg = "✓ {$summary}";
        echo php_sapi_name() === 'cli' ? "$msg\n" : "<div class='success'>$msg</div>";
    }
    
    // Show recent migrations log
    if (php_sapi_name() !== 'cli') {
        echo "<h2>Recent Migrations Log</h2>";
        try {
            $stmt = $db->query("
                SELECT migration_file, executed_at, execution_time_ms, status, error_message 
                FROM migrations_log 
                ORDER BY executed_at DESC 
                LIMIT 20
            ");
            $recentMigrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($recentMigrations)) {
                echo "<table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>";
                echo "<tr style='background: #f8f9fa;'><th style='padding: 8px; text-align: left; border: 1px solid #ddd;'>File</th><th style='padding: 8px; text-align: left; border: 1px solid #ddd;'>Executed</th><th style='padding: 8px; text-align: left; border: 1px solid #ddd;'>Time</th><th style='padding: 8px; text-align: left; border: 1px solid #ddd;'>Status</th></tr>";
                foreach ($recentMigrations as $migration) {
                    $statusColor = $migration['status'] === 'success' ? '#28a745' : ($migration['status'] === 'error' ? '#dc3545' : '#ffc107');
                    echo "<tr>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$migration['migration_file']}</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$migration['executed_at']}</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$migration['execution_time_ms']}ms</td>";
                    echo "<td style='padding: 8px; border: 1px solid #ddd; color: {$statusColor};'><strong>{$migration['status']}</strong></td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } catch (\PDOException $e) {
            echo "<div class='info'>Could not load migration log: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    if (php_sapi_name() !== 'cli') {
        echo "<p style='margin-top: 20px;'><a href='javascript:location.reload()'>Run Again</a> | <a href='" . (defined('BASE_URL_PATH') ? BASE_URL_PATH : '') . "/dashboard'>Go to Dashboard</a></p>";
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

