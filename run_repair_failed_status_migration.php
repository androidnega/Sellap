<?php
/**
 * Web-accessible migration runner for Repair Failed Status
 * Access via: http://localhost/sellapp/run_repair_failed_status_migration.php
 * 
 * This will automatically run the migration to add 'failed' status to repairs_new table
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Repair Failed Status Migration</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
        .warning { color: #ffc107; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Repair Failed Status Migration</h1>
    <hr>
    <pre>";

require_once __DIR__ . '/config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    
    echo "📊 Database: {$dbName}\n\n";
    
    // Check if repairs_new table exists
    $tableExists = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'repairs_new'
    ")->fetchColumn();
    
    if ($tableExists == 0) {
        echo "❌ ERROR: repairs_new table does not exist!\n";
        echo "   Please create the repairs_new table first.\n";
        exit;
    }
    
    echo "✓ repairs_new table exists\n\n";
    
    // Check current status column definition
    echo "📋 Checking current status column definition...\n";
    $columnInfo = $db->query("
        SELECT COLUMN_TYPE 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'repairs_new' 
        AND COLUMN_NAME = 'status'
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($columnInfo) {
        $currentType = $columnInfo['COLUMN_TYPE'];
        echo "   Current status column type: {$currentType}\n";
        
        // Check if 'failed' is already in the ENUM
        if (strpos($currentType, "'failed'") !== false) {
            echo "\nℹ️  Migration already applied - 'failed' status exists\n";
            echo "✅ Migration already complete!\n";
            exit;
        }
    } else {
        echo "   ⚠ Could not find status column definition\n";
    }
    
    echo "\n📋 Executing migration...\n\n";
    
    // Execute the ALTER TABLE statement
    try {
        echo "Step 1: Modifying status column to include 'failed' status...\n";
        $db->exec("
            ALTER TABLE repairs_new 
            MODIFY COLUMN status ENUM('pending','in_progress','completed','delivered','cancelled','failed') DEFAULT 'pending'
        ");
        echo "   ✓ Status column modified successfully\n";
        
        // Verify the change
        echo "\n🔍 Verifying migration...\n";
        $newColumnInfo = $db->query("
            SELECT COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '{$dbName}' 
            AND TABLE_NAME = 'repairs_new' 
            AND COLUMN_NAME = 'status'
        ")->fetch(PDO::FETCH_ASSOC);
        
        if ($newColumnInfo) {
            $newType = $newColumnInfo['COLUMN_TYPE'];
            echo "   New status column type: {$newType}\n";
            
            if (strpos($newType, "'failed'") !== false) {
                echo "\n✅ Migration completed successfully!\n\n";
                echo "🎉 The 'failed' status has been added to the repairs_new table.\n";
                echo "   Technicians can now mark repairs as failed, and customers will be notified via SMS.\n";
            } else {
                echo "\n⚠️  Warning: Migration executed but 'failed' status not found in new definition.\n";
            }
        } else {
            echo "\n⚠️  Warning: Could not verify migration status.\n";
        }
        
    } catch (\PDOException $e) {
        echo "\n❌ ERROR: " . $e->getMessage() . "\n";
        echo "\n   Please check the error above and try again.\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\n   Stack trace:\n";
    echo $e->getTraceAsString();
}

echo "</pre>
</div>
</body>
</html>";

