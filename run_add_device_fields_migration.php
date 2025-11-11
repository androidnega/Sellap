<?php
/**
 * Migration runner for adding device brand and model fields to repairs_new
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Add Device Fields Migration</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Add Device Fields Migration</h1>
    <hr>
    <pre>";

try {
    $db = \Database::getInstance()->getConnection();
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    
    echo "📊 Database: {$dbName}\n\n";
    
    // Check if columns exist
    $checkBrand = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'repairs_new' 
        AND COLUMN_NAME = 'device_brand'
    ")->fetchColumn();
    
    $checkModel = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'repairs_new' 
        AND COLUMN_NAME = 'device_model'
    ")->fetchColumn();
    
    if ($checkBrand > 0 && $checkModel > 0) {
        echo "ℹ️  Migration already applied - columns exist\n";
        echo "✅ Migration already complete!\n";
        exit;
    }
    
    echo "📋 Executing migration...\n\n";
    
    if ($checkBrand == 0) {
        echo "Adding device_brand column...\n";
        $db->exec("ALTER TABLE repairs_new ADD COLUMN device_brand VARCHAR(100) NULL AFTER product_id");
        echo "   ✓ device_brand column added\n";
    } else {
        echo "device_brand already exists (skipping)\n";
    }
    
    if ($checkModel == 0) {
        echo "Adding device_model column...\n";
        $db->exec("ALTER TABLE repairs_new ADD COLUMN device_model VARCHAR(100) NULL AFTER device_brand");
        echo "   ✓ device_model column added\n";
    } else {
        echo "device_model already exists (skipping)\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>
</div>
</body>
</html>";

