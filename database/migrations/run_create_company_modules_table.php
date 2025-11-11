<?php
/**
 * Run Migration: Create company_modules table
 * This script executes the SQL migration using the application's database connection
 */

require_once __DIR__ . '/../../config/database.php';

echo "🚀 Running Migration: Create company_modules table\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $db = \Database::getInstance()->getConnection();
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    
    echo "📊 Database: {$dbName}\n\n";
    
    // Check if table already exists
    $tableCheck = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'company_modules'
    ")->fetchColumn();
    
    if ($tableCheck > 0) {
        echo "ℹ️  Table 'company_modules' already exists\n";
        echo "✅ Migration already applied!\n";
        exit(0);
    }
    
    echo "📋 Creating company_modules table...\n\n";
    
    // Build the CREATE TABLE statement directly (more reliable than parsing SQL file)
    $createTableSQL = "CREATE TABLE IF NOT EXISTS company_modules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id BIGINT UNSIGNED NOT NULL,
        module_key VARCHAR(100) NOT NULL,
        enabled BOOLEAN NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        UNIQUE KEY unique_company_module (company_id, module_key),
        INDEX idx_company_id (company_id),
        INDEX idx_module_key (module_key),
        INDEX idx_enabled (enabled),
        INDEX idx_company_enabled (company_id, enabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $db->exec($createTableSQL);
        echo "✓ Table creation statement executed successfully\n";
    } catch (\PDOException $e) {
        // If table already exists, that's okay
        if (strpos($e->getMessage(), 'already exists') !== false || 
            strpos($e->getMessage(), 'Duplicate table') !== false) {
            echo "ℹ️  Table already exists (this is fine)\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "📝 Table 'company_modules' has been created\n";
    
    // Verify table was created
    $verify = $db->query("SHOW TABLES LIKE 'company_modules'")->fetchColumn();
    if ($verify) {
        echo "✓ Verification: Table exists\n";
        
        // Show table structure
        $columns = $db->query("DESCRIBE company_modules")->fetchAll(\PDO::FETCH_ASSOC);
        echo "\n📊 Table Structure:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
    }
    
} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

