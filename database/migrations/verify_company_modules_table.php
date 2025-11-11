<?php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    echo "🔍 Verifying company_modules table...\n\n";
    
    // Check if table exists
    $exists = $db->query("SHOW TABLES LIKE 'company_modules'")->fetchColumn();
    
    if (!$exists) {
        echo "❌ Table 'company_modules' does NOT exist!\n";
        exit(1);
    }
    
    echo "✅ Table 'company_modules' exists!\n\n";
    
    // Show table structure
    $columns = $db->query("DESCRIBE company_modules")->fetchAll(\PDO::FETCH_ASSOC);
    echo "📊 Table Structure:\n";
    echo str_repeat("-", 60) . "\n";
    printf("%-20s %-25s %-10s\n", "Field", "Type", "Null");
    echo str_repeat("-", 60) . "\n";
    foreach ($columns as $column) {
        printf("%-20s %-25s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null']
        );
    }
    echo str_repeat("-", 60) . "\n\n";
    
    // Show indexes
    $indexes = $db->query("SHOW INDEX FROM company_modules")->fetchAll(\PDO::FETCH_ASSOC);
    if (!empty($indexes)) {
        echo "🔑 Indexes:\n";
        foreach ($indexes as $index) {
            echo "  - {$index['Key_name']} on {$index['Column_name']}\n";
        }
        echo "\n";
    }
    
    echo "✅ Verification complete! Table is ready to use.\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

