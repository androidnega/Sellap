<?php
/**
 * Simplified Migration Runner - outputs to file for verification
 */

require_once __DIR__ . '/../../config/database.php';

$logFile = __DIR__ . '/migration_output.log';
$fp = fopen($logFile, 'w');

function logMsg($msg, $fp) {
    echo $msg . "\n";
    fwrite($fp, $msg . "\n");
}

logMsg("============================================", $fp);
logMsg("Migration: Add Sale IDs to Swap Profit Links", $fp);
logMsg("Started: " . date('Y-m-d H:i:s'), $fp);
logMsg("============================================", $fp);
logMsg("", $fp);

try {
    $db = \Database::getInstance()->getConnection();
    
    // Get database name
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    logMsg("Database: {$dbName}", $fp);
    logMsg("", $fp);
    
    // Check if swap_profit_links table exists
    $tableExists = $db->query("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = '{$dbName}' 
        AND TABLE_NAME = 'swap_profit_links'
    ")->fetchColumn();
    
    if ($tableExists == 0) {
        logMsg("ERROR: swap_profit_links table does not exist!", $fp);
        fclose($fp);
        exit(1);
    }
    
    logMsg("OK: swap_profit_links table exists", $fp);
    logMsg("", $fp);
    
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
        logMsg("INFO: Migration already applied - columns exist", $fp);
        logMsg("  - company_item_sale_id", $fp);
        logMsg("  - customer_item_sale_id", $fp);
        logMsg("", $fp);
        logMsg("Migration already complete!", $fp);
        fclose($fp);
        exit(0);
    }
    
    logMsg("Executing migration steps...", $fp);
    logMsg("", $fp);
    
    $db->beginTransaction();
    $success = true;
    
    // Step 1: Add company_item_sale_id column
    if ($checkCompany == 0) {
        logMsg("Step 1: Adding company_item_sale_id column...", $fp);
        try {
            $db->exec("ALTER TABLE swap_profit_links ADD COLUMN company_item_sale_id BIGINT UNSIGNED NULL AFTER swap_id");
            logMsg("  OK: company_item_sale_id column added", $fp);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                logMsg("  ERROR: " . $e->getMessage(), $fp);
                $success = false;
            } else {
                logMsg("  INFO: Column already exists (skipping)", $fp);
            }
        }
    } else {
        logMsg("Step 1: company_item_sale_id already exists (skipping)", $fp);
    }
    
    // Step 2: Add customer_item_sale_id column
    if ($checkCustomer == 0) {
        logMsg("Step 2: Adding customer_item_sale_id column...", $fp);
        try {
            $db->exec("ALTER TABLE swap_profit_links ADD COLUMN customer_item_sale_id BIGINT UNSIGNED NULL AFTER company_item_sale_id");
            logMsg("  OK: customer_item_sale_id column added", $fp);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                logMsg("  ERROR: " . $e->getMessage(), $fp);
                $success = false;
            } else {
                logMsg("  INFO: Column already exists (skipping)", $fp);
            }
        }
    } else {
        logMsg("Step 2: customer_item_sale_id already exists (skipping)", $fp);
    }
    
    // Step 3: Add foreign keys and indexes
    if ($success) {
        logMsg("Step 3: Adding foreign keys and indexes...", $fp);
        
        $posSalesExists = $db->query("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = '{$dbName}' 
            AND TABLE_NAME = 'pos_sales'
        ")->fetchColumn();
        
        if ($posSalesExists > 0) {
            // Add foreign keys
            try {
                $db->exec("ALTER TABLE swap_profit_links ADD CONSTRAINT fk_company_item_sale FOREIGN KEY (company_item_sale_id) REFERENCES pos_sales(id) ON DELETE SET NULL");
                logMsg("  OK: Foreign key fk_company_item_sale created", $fp);
            } catch (\PDOException $e) {
                logMsg("  INFO: Foreign key already exists or error: " . $e->getMessage(), $fp);
            }
            
            try {
                $db->exec("ALTER TABLE swap_profit_links ADD CONSTRAINT fk_customer_item_sale FOREIGN KEY (customer_item_sale_id) REFERENCES pos_sales(id) ON DELETE SET NULL");
                logMsg("  OK: Foreign key fk_customer_item_sale created", $fp);
            } catch (\PDOException $e) {
                logMsg("  INFO: Foreign key already exists or error: " . $e->getMessage(), $fp);
            }
        }
        
        // Add indexes
        try {
            $db->exec("CREATE INDEX idx_company_item_sale ON swap_profit_links(company_item_sale_id)");
            logMsg("  OK: Index idx_company_item_sale created", $fp);
        } catch (\PDOException $e) {
            logMsg("  INFO: Index already exists or error: " . $e->getMessage(), $fp);
        }
        
        try {
            $db->exec("CREATE INDEX idx_customer_item_sale ON swap_profit_links(customer_item_sale_id)");
            logMsg("  OK: Index idx_customer_item_sale created", $fp);
        } catch (\PDOException $e) {
            logMsg("  INFO: Index already exists or error: " . $e->getMessage(), $fp);
        }
    }
    
    if ($success) {
        $db->commit();
        logMsg("", $fp);
        logMsg("Migration executed successfully!", $fp);
        logMsg("", $fp);
        logMsg("Verifying...", $fp);
        
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
            logMsg("  OK: company_item_sale_id verified", $fp);
            logMsg("  OK: customer_item_sale_id verified", $fp);
            logMsg("", $fp);
            logMsg("Migration completed successfully!", $fp);
            logMsg("The swap profit tracking system is now ready to use.", $fp);
        } else {
            logMsg("WARNING: Columns not found after migration", $fp);
            $success = false;
        }
    } else {
        $db->rollBack();
        logMsg("", $fp);
        logMsg("Migration failed - transaction rolled back", $fp);
    }
    
} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logMsg("", $fp);
    logMsg("ERROR: " . $e->getMessage(), $fp);
    logMsg("Trace: " . $e->getTraceAsString(), $fp);
    $success = false;
}

logMsg("", $fp);
logMsg("Completed: " . date('Y-m-d H:i:s'), $fp);
logMsg("============================================", $fp);

fclose($fp);

if ($success) {
    exit(0);
} else {
    exit(1);
}

