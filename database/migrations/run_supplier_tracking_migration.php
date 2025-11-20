<?php
/**
 * Run Supplier Tracking Migration
 * This script creates the supplier_product_tracking table and adds supplier_id to products
 * 
 * Usage: php database/migrations/run_supplier_tracking_migration.php
 * Or visit: http://localhost/sellapp/database/migrations/run_supplier_tracking_migration.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if running from CLI or web
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    echo "<html><head><title>Run Supplier Tracking Migration</title><style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style></head><body>";
}

echo $isCli 
    ? "=== Running Supplier Tracking Migration ===\n\n" 
    : "<h1>Running Supplier Tracking Migration</h1>";

try {
    // Load database configuration
    require_once __DIR__ . '/../../config/database.php';
    
    $sqlFile = __DIR__ . '/create_supplier_tracking.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    if (empty($sql)) {
        throw new Exception("Migration file is empty");
    }
    
    // Get database connection
    $db = \Database::getInstance()->getConnection();
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            // Handle prepared statements (SET @variable)
            if (preg_match('/^SET\s+@/', $statement)) {
                $db->exec($statement);
            } else {
                // For ALTER TABLE with dynamic column check
                if (preg_match('/PREPARE|EXECUTE|DEALLOCATE/', $statement)) {
                    $db->exec($statement);
                } else {
                    $db->exec($statement);
                }
            }
            $successCount++;
        } catch (\PDOException $e) {
            // Ignore "table already exists" or "column already exists" errors
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate column') === false) {
                $errorCount++;
                $errors[] = $e->getMessage();
                if ($isCli) {
                    echo "Error: " . $e->getMessage() . "\n";
                } else {
                    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            } else {
                $successCount++;
            }
        }
    }
    
    if ($isCli) {
        echo "\n=== Migration Complete ===\n";
        echo "Successful: $successCount\n";
        if ($errorCount > 0) {
            echo "Errors: $errorCount\n";
        }
    } else {
        echo "<div class='success'><strong>✓ Migration completed successfully!</strong><br><br>";
        echo "Successful statements: $successCount<br>";
        if ($errorCount > 0) {
            echo "Errors: $errorCount<br>";
        }
        echo "<br>You can now use supplier tracking features in the inventory system.</div>";
    }
    
} catch (Exception $e) {
    if ($isCli) {
        echo "Fatal Error: " . $e->getMessage() . "\n";
    } else {
        echo "<div class='error'><strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    exit(1);
}

if (!$isCli) {
    echo "</body></html>";
}


