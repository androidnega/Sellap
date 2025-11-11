<?php
/**
 * Web-accessible migration runner
 * Access via: http://localhost/sellapp/run_migration.php
 * 
 * This will automatically run the migration to add sale ID columns to swap_profit_links table
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Swap Profit Migration</title>
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
    <h1>🚀 Swap Profit Migration Runner</h1>
    <hr>
    <pre>";

require_once __DIR__ . '/config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    
    echo "📊 Database: {$dbName}\n\n";
    
    // Check if swap_profit_links table exists
    $tableExists = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'swap_profit_links'
    ")->fetchColumn();
    
    if ($tableExists == 0) {
        echo "❌ ERROR: swap_profit_links table does not exist!\n";
        echo "   Please create the swap_profit_links table first.\n";
        exit;
    }
    
    echo "✓ swap_profit_links table exists\n\n";
    
    // Check if columns already exist
    $checkCompany = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'swap_profit_links' 
        AND COLUMN_NAME = 'company_item_sale_id'
    ")->fetchColumn();
    
    $checkCustomer = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'swap_profit_links' 
        AND COLUMN_NAME = 'customer_item_sale_id'
    ")->fetchColumn();
    
    if ($checkCompany > 0 && $checkCustomer > 0) {
        echo "ℹ️  Migration already applied - columns exist\n";
        echo "   ✓ company_item_sale_id\n";
        echo "   ✓ customer_item_sale_id\n";
        echo "\n✅ Migration already complete!\n";
        exit;
    }
    
    echo "📋 Executing migration steps...\n\n";
    
    $db->beginTransaction();
    $success = true;
    
    // Step 1: Add company_item_sale_id column
    if ($checkCompany == 0) {
        echo "Step 1: Adding company_item_sale_id column...\n";
        try {
            $db->exec("ALTER TABLE swap_profit_links ADD COLUMN company_item_sale_id BIGINT UNSIGNED NULL AFTER swap_id");
            echo "   ✓ company_item_sale_id column added\n";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                echo "   ❌ ERROR: " . $e->getMessage() . "\n";
                $success = false;
            } else {
                echo "   ⚠ Column already exists (skipping)\n";
            }
        }
    } else {
        echo "Step 1: company_item_sale_id already exists (skipping)\n";
    }
    
    // Step 2: Add customer_item_sale_id column
    if ($checkCustomer == 0) {
        echo "Step 2: Adding customer_item_sale_id column...\n";
        try {
            $db->exec("ALTER TABLE swap_profit_links ADD COLUMN customer_item_sale_id BIGINT UNSIGNED NULL AFTER company_item_sale_id");
            echo "   ✓ customer_item_sale_id column added\n";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                echo "   ❌ ERROR: " . $e->getMessage() . "\n";
                $success = false;
            } else {
                echo "   ⚠ Column already exists (skipping)\n";
            }
        }
    } else {
        echo "Step 2: customer_item_sale_id already exists (skipping)\n";
    }
    
    // Step 3: Add foreign keys
    if ($success) {
        echo "Step 3: Adding foreign key constraints...\n";
        
        $posSalesExists = $db->query("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = '{$dbName}' 
            AND TABLE_NAME = 'pos_sales'
        ")->fetchColumn();
        
        if ($posSalesExists > 0) {
            try {
                $db->exec("ALTER TABLE swap_profit_links ADD CONSTRAINT fk_company_item_sale FOREIGN KEY (company_item_sale_id) REFERENCES pos_sales(id) ON DELETE SET NULL");
                echo "   ✓ Foreign key fk_company_item_sale created\n";
            } catch (\PDOException $e) {
                echo "   ⚠ Foreign key already exists or error: " . substr($e->getMessage(), 0, 80) . "...\n";
            }
            
            try {
                $db->exec("ALTER TABLE swap_profit_links ADD CONSTRAINT fk_customer_item_sale FOREIGN KEY (customer_item_sale_id) REFERENCES pos_sales(id) ON DELETE SET NULL");
                echo "   ✓ Foreign key fk_customer_item_sale created\n";
            } catch (\PDOException $e) {
                echo "   ⚠ Foreign key already exists or error: " . substr($e->getMessage(), 0, 80) . "...\n";
            }
        }
        
        // Step 4: Add indexes
        echo "Step 4: Adding indexes...\n";
        try {
            $db->exec("CREATE INDEX idx_company_item_sale ON swap_profit_links(company_item_sale_id)");
            echo "   ✓ Index idx_company_item_sale created\n";
        } catch (\PDOException $e) {
            echo "   ⚠ Index already exists or error: " . substr($e->getMessage(), 0, 80) . "...\n";
        }
        
        try {
            $db->exec("CREATE INDEX idx_customer_item_sale ON swap_profit_links(customer_item_sale_id)");
            echo "   ✓ Index idx_customer_item_sale created\n";
        } catch (\PDOException $e) {
            echo "   ⚠ Index already exists or error: " . substr($e->getMessage(), 0, 80) . "...\n";
        }
    }
    
    if ($success) {
        // Check if transaction is still active before committing
        // Note: DDL statements (ALTER TABLE, CREATE INDEX) auto-commit in MySQL
        if ($db->inTransaction()) {
            $db->commit();
        }
        echo "\n✅ Migration executed successfully!\n\n";
        
        // Verify
        echo "🔍 Verifying migration...\n";
        $companyExists = $db->query("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '{$dbName}' 
            AND TABLE_NAME = 'swap_profit_links' 
            AND COLUMN_NAME = 'company_item_sale_id'
        ")->fetchColumn();
        
        $customerExists = $db->query("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '{$dbName}' 
            AND TABLE_NAME = 'swap_profit_links' 
            AND COLUMN_NAME = 'customer_item_sale_id'
        ")->fetchColumn();
        
        if ($companyExists > 0 && $customerExists > 0) {
            echo "   ✓ company_item_sale_id verified\n";
            echo "   ✓ customer_item_sale_id verified\n";
            echo "\n🎉 Migration completed successfully!\n";
            echo "   The swap profit tracking system is now ready to use.\n";
            echo "   New swaps will automatically track sale IDs and calculate profit.\n";
        }
    } else {
        // Check if transaction is still active before rolling back
        if ($db->inTransaction()) {
            $db->rollBack();
            echo "\n❌ Migration failed - transaction rolled back\n";
        } else {
            echo "\n❌ Migration failed (some steps may have been applied due to auto-commit)\n";
        }
    }
    
} catch (\Exception $e) {
    // Check if transaction is still active before rolling back
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>
</div>
</body>
</html>";

