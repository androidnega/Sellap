<?php
/**
 * Automatic Migration Runner for Add Sale IDs to Swap Profit Links
 * This script executes the migration logic directly
 */

require_once __DIR__ . '/../../config/database.php';

$db = \Database::getInstance()->getConnection();

echo "🚀 Running Migration: Add Sale IDs to Swap Profit Links\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Get database name
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
        exit(1);
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
        exit(0);
    }
    
    echo "📋 Executing migration steps...\n\n";
    
    // Begin transaction for safety
    $db->beginTransaction();
    
    // Step 1: Add company_item_sale_id column
    if ($checkCompany == 0) {
        echo "   Step 1: Adding company_item_sale_id column...\n";
        try {
            $db->exec("ALTER TABLE swap_profit_links ADD COLUMN company_item_sale_id BIGINT UNSIGNED NULL AFTER swap_id");
            echo "      ✓ company_item_sale_id column added\n";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                throw $e;
            }
            echo "      ⚠ company_item_sale_id column already exists (skipping)\n";
        }
    } else {
        echo "   Step 1: company_item_sale_id column already exists (skipping)\n";
    }
    
    // Step 2: Add customer_item_sale_id column
    if ($checkCustomer == 0) {
        echo "   Step 2: Adding customer_item_sale_id column...\n";
        try {
            $db->exec("ALTER TABLE swap_profit_links ADD COLUMN customer_item_sale_id BIGINT UNSIGNED NULL AFTER company_item_sale_id");
            echo "      ✓ customer_item_sale_id column added\n";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                throw $e;
            }
            echo "      ⚠ customer_item_sale_id column already exists (skipping)\n";
        }
    } else {
        echo "   Step 2: customer_item_sale_id column already exists (skipping)\n";
    }
    
    // Step 3: Add foreign key constraints
    echo "   Step 3: Adding foreign key constraints...\n";
    
    // Check if pos_sales table exists
    $posSalesExists = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'pos_sales'
    ")->fetchColumn();
    
    if ($posSalesExists > 0) {
        // Check if foreign keys already exist
        $fkCompanyExists = $db->query("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = '{$dbName}' 
            AND TABLE_NAME = 'swap_profit_links' 
            AND CONSTRAINT_NAME = 'fk_company_item_sale'
        ")->fetchColumn();
        
        if ($fkCompanyExists == 0) {
            try {
                $db->exec("ALTER TABLE swap_profit_links ADD CONSTRAINT fk_company_item_sale FOREIGN KEY (company_item_sale_id) REFERENCES pos_sales(id) ON DELETE SET NULL");
                echo "      ✓ Foreign key fk_company_item_sale created\n";
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false && strpos($e->getMessage(), 'already exists') === false) {
                    echo "      ⚠ Could not create fk_company_item_sale: " . $e->getMessage() . "\n";
                } else {
                    echo "      ⚠ Foreign key fk_company_item_sale already exists (skipping)\n";
                }
            }
        } else {
            echo "      ⚠ Foreign key fk_company_item_sale already exists (skipping)\n";
        }
        
        $fkCustomerExists = $db->query("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = '{$dbName}' 
            AND TABLE_NAME = 'swap_profit_links' 
            AND CONSTRAINT_NAME = 'fk_customer_item_sale'
        ")->fetchColumn();
        
        if ($fkCustomerExists == 0) {
            try {
                $db->exec("ALTER TABLE swap_profit_links ADD CONSTRAINT fk_customer_item_sale FOREIGN KEY (customer_item_sale_id) REFERENCES pos_sales(id) ON DELETE SET NULL");
                echo "      ✓ Foreign key fk_customer_item_sale created\n";
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false && strpos($e->getMessage(), 'already exists') === false) {
                    echo "      ⚠ Could not create fk_customer_item_sale: " . $e->getMessage() . "\n";
                } else {
                    echo "      ⚠ Foreign key fk_customer_item_sale already exists (skipping)\n";
                }
            }
        } else {
            echo "      ⚠ Foreign key fk_customer_item_sale already exists (skipping)\n";
        }
    } else {
        echo "      ⚠ pos_sales table not found - skipping foreign key constraints\n";
    }
    
    // Step 4: Add indexes
    echo "   Step 4: Adding indexes...\n";
    
    $idxCompanyExists = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'swap_profit_links' 
        AND INDEX_NAME = 'idx_company_item_sale'
    ")->fetchColumn();
    
    if ($idxCompanyExists == 0) {
        try {
            $db->exec("CREATE INDEX idx_company_item_sale ON swap_profit_links(company_item_sale_id)");
            echo "      ✓ Index idx_company_item_sale created\n";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                echo "      ⚠ Could not create index: " . $e->getMessage() . "\n";
            } else {
                echo "      ⚠ Index idx_company_item_sale already exists (skipping)\n";
            }
        }
    } else {
        echo "      ⚠ Index idx_company_item_sale already exists (skipping)\n";
    }
    
    $idxCustomerExists = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'swap_profit_links' 
        AND INDEX_NAME = 'idx_customer_item_sale'
    ")->fetchColumn();
    
    if ($idxCustomerExists == 0) {
        try {
            $db->exec("CREATE INDEX idx_customer_item_sale ON swap_profit_links(customer_item_sale_id)");
            echo "      ✓ Index idx_customer_item_sale created\n";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                echo "      ⚠ Could not create index: " . $e->getMessage() . "\n";
            } else {
                echo "      ⚠ Index idx_customer_item_sale already exists (skipping)\n";
            }
        }
    } else {
        echo "      ⚠ Index idx_customer_item_sale already exists (skipping)\n";
    }
    
    // Check if transaction is still active before committing
    // Note: DDL statements (ALTER TABLE, CREATE INDEX) auto-commit in MySQL
    if ($db->inTransaction()) {
        $db->commit();
    }
    
    echo "\n✅ Migration executed successfully!\n\n";
    
    // Verify the migration
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
        echo "   ✓ company_item_sale_id column verified\n";
        echo "   ✓ customer_item_sale_id column verified\n";
        
        echo "\n🎉 Migration completed successfully!\n";
        echo "   The swap profit tracking system is now ready to use.\n";
        echo "   New swaps will automatically track sale IDs and calculate profit.\n";
    } else {
        echo "⚠️  WARNING: Migration executed but columns not found. Please check manually.\n";
        exit(1);
    }
    
} catch (\PDOException $e) {
    // Check if transaction is still active before rolling back
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ ERROR: Migration failed\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    // Check if transaction is still active before rolling back
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n❌ ERROR: Unexpected error\n";
    echo "   Message: " . $e->getMessage() . "\n";
    exit(1);
}

