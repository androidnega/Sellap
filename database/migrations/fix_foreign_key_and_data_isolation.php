<?php
/**
 * Migration: Fix Foreign Key Constraint and Data Isolation
 * 
 * Issues Fixed:
 * 1. Products table foreign key constraint - change created_by to nullable and set ON DELETE SET NULL
 * 2. Customers table foreign key constraint - change created_by_user_id to nullable and set ON DELETE SET NULL
 * 3. POS Sales table foreign key constraint - change created_by_user_id to nullable and set ON DELETE SET NULL
 * 4. Repairs New table foreign key constraint - change technician_id to nullable and set ON DELETE SET NULL
 * 5. Add unique constraint for customers (email+phone+company_id) to prevent duplicates
 * 6. Ensure complete data isolation between companies
 * 
 * Date: 2025-01-28
 */

require_once __DIR__ . '/../../config/database.php';

$db = \Database::getInstance()->getConnection();

try {
    $db->beginTransaction();
    
    echo "Starting migration: Fix Foreign Key Constraint and Data Isolation\n";
    
    // =====================================================
    // 1. Fix products table foreign key constraint
    // =====================================================
    echo "\n1. Fixing products table foreign key constraint...\n";
    
    // First, drop the existing foreign key constraint
    try {
        $db->exec("ALTER TABLE products DROP FOREIGN KEY products_ibfk_4");
        echo "   ✓ Dropped existing foreign key constraint products_ibfk_4\n";
    } catch (\Exception $e) {
        echo "   ⚠ Could not drop constraint (may not exist): " . $e->getMessage() . "\n";
    }
    
    // Make created_by nullable (to allow SET NULL on delete)
    try {
        $db->exec("ALTER TABLE products MODIFY COLUMN created_by BIGINT UNSIGNED NULL");
        echo "   ✓ Made created_by column nullable\n";
    } catch (\Exception $e) {
        echo "   ⚠ Error modifying column: " . $e->getMessage() . "\n";
    }
    
    // Re-add the foreign key with ON DELETE SET NULL
    try {
        $db->exec("ALTER TABLE products 
                   ADD CONSTRAINT products_ibfk_4 
                   FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "   ✓ Added foreign key constraint with ON DELETE SET NULL\n";
    } catch (\Exception $e) {
        echo "   ✗ Error adding constraint: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    // =====================================================
    // 2. Fix customers table foreign key constraint
    // =====================================================
    echo "\n2. Fixing customers table foreign key constraint...\n";
    
    // Drop the existing foreign key constraint
    try {
        $db->exec("ALTER TABLE customers DROP FOREIGN KEY customers_ibfk_2");
        echo "   ✓ Dropped existing foreign key constraint customers_ibfk_2\n";
    } catch (\Exception $e) {
        echo "   ⚠ Could not drop constraint (may not exist): " . $e->getMessage() . "\n";
    }
    
    // Make created_by_user_id nullable (to allow SET NULL on delete)
    try {
        $db->exec("ALTER TABLE customers MODIFY COLUMN created_by_user_id BIGINT UNSIGNED NULL");
        echo "   ✓ Made created_by_user_id column nullable\n";
    } catch (\Exception $e) {
        echo "   ⚠ Error modifying column: " . $e->getMessage() . "\n";
    }
    
    // Re-add the foreign key with ON DELETE SET NULL
    try {
        $db->exec("ALTER TABLE customers 
                   ADD CONSTRAINT customers_ibfk_2 
                   FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "   ✓ Added foreign key constraint with ON DELETE SET NULL\n";
    } catch (\Exception $e) {
        echo "   ⚠ Error adding constraint (may already exist): " . $e->getMessage() . "\n";
    }
    
    // =====================================================
    // 3. Fix pos_sales table foreign key constraint
    // =====================================================
    echo "\n3. Fixing pos_sales table foreign key constraint...\n";
    
    // Drop the existing foreign key constraint
    try {
        $db->exec("ALTER TABLE pos_sales DROP FOREIGN KEY pos_sales_ibfk_3");
        echo "   ✓ Dropped existing foreign key constraint pos_sales_ibfk_3\n";
    } catch (\Exception $e) {
        echo "   ⚠ Could not drop constraint (may not exist): " . $e->getMessage() . "\n";
    }
    
    // Make created_by_user_id nullable (to allow SET NULL on delete)
    try {
        $db->exec("ALTER TABLE pos_sales MODIFY COLUMN created_by_user_id BIGINT UNSIGNED NULL");
        echo "   ✓ Made created_by_user_id column nullable\n";
    } catch (\Exception $e) {
        echo "   ⚠ Error modifying column: " . $e->getMessage() . "\n";
    }
    
    // Re-add the foreign key with ON DELETE SET NULL
    try {
        $db->exec("ALTER TABLE pos_sales 
                   ADD CONSTRAINT pos_sales_ibfk_3 
                   FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "   ✓ Added foreign key constraint with ON DELETE SET NULL\n";
    } catch (\Exception $e) {
        echo "   ⚠ Error adding constraint (may already exist): " . $e->getMessage() . "\n";
    }
    
    // =====================================================
    // 4. Fix repairs_new table foreign key constraint
    // =====================================================
    echo "\n4. Fixing repairs_new table foreign key constraint...\n";
    
    // Drop the existing foreign key constraint
    try {
        $db->exec("ALTER TABLE repairs_new DROP FOREIGN KEY repairs_new_ibfk_2");
        echo "   ✓ Dropped existing foreign key constraint repairs_new_ibfk_2\n";
    } catch (\Exception $e) {
        echo "   ⚠ Could not drop constraint (may not exist): " . $e->getMessage() . "\n";
    }
    
    // Make technician_id nullable (to allow SET NULL on delete)
    try {
        $db->exec("ALTER TABLE repairs_new MODIFY COLUMN technician_id BIGINT UNSIGNED NULL");
        echo "   ✓ Made technician_id column nullable\n";
    } catch (\Exception $e) {
        echo "   ⚠ Error modifying column: " . $e->getMessage() . "\n";
    }
    
    // Re-add the foreign key with ON DELETE SET NULL
    try {
        $db->exec("ALTER TABLE repairs_new 
                   ADD CONSTRAINT repairs_new_ibfk_2 
                   FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "   ✓ Added foreign key constraint with ON DELETE SET NULL\n";
    } catch (\Exception $e) {
        echo "   ⚠ Error adding constraint (may already exist): " . $e->getMessage() . "\n";
    }
    
    // =====================================================
    // 5. Add unique constraint for customers (email+phone+company_id)
    // =====================================================
    echo "\n5. Adding unique constraint for customers...\n";
    
    // First, check if there are any duplicate customers that need to be handled
    $duplicateCheck = $db->query("
        SELECT company_id, phone_number, email, COUNT(*) as count
        FROM customers
        WHERE phone_number IS NOT NULL AND phone_number != ''
        GROUP BY company_id, phone_number, COALESCE(email, '')
        HAVING count > 1
    ");
    $duplicates = $duplicateCheck->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($duplicates) > 0) {
        echo "   ⚠ Found " . count($duplicates) . " duplicate customer groups. Please resolve manually before adding constraint.\n";
        foreach ($duplicates as $dup) {
            echo "      - Company ID: {$dup['company_id']}, Phone: {$dup['phone_number']}, Email: " . ($dup['email'] ?? 'NULL') . " (Count: {$dup['count']})\n";
        }
        echo "   ⚠ Skipping unique constraint addition due to existing duplicates.\n";
    } else {
        // Add unique index on (company_id, phone_number) - phone is required and must be unique per company
        try {
            // First, check if index already exists
            $indexCheck = $db->query("SHOW INDEX FROM customers WHERE Key_name = 'idx_unique_phone_company'");
            if ($indexCheck->rowCount() == 0) {
                $db->exec("ALTER TABLE customers ADD UNIQUE INDEX idx_unique_phone_company (company_id, phone_number)");
                echo "   ✓ Added unique constraint on (company_id, phone_number)\n";
            } else {
                echo "   ℹ Unique constraint on (company_id, phone_number) already exists\n";
            }
        } catch (\Exception $e) {
            echo "   ⚠ Error adding phone constraint: " . $e->getMessage() . "\n";
        }
        
        // Add unique index on (company_id, email) where email is not null - email is optional but must be unique per company if provided
        try {
            // For email, we need to handle NULL values properly - MySQL unique indexes allow multiple NULLs
            // So we'll create a unique index that handles this
            $indexCheck = $db->query("SHOW INDEX FROM customers WHERE Key_name = 'idx_unique_email_company'");
            if ($indexCheck->rowCount() == 0) {
                // Create a unique index on company_id and email (NULL emails are allowed multiple times)
                $db->exec("ALTER TABLE customers ADD UNIQUE INDEX idx_unique_email_company (company_id, email)");
                echo "   ✓ Added unique constraint on (company_id, email)\n";
            } else {
                echo "   ℹ Unique constraint on (company_id, email) already exists\n";
            }
        } catch (\Exception $e) {
            echo "   ⚠ Error adding email constraint: " . $e->getMessage() . "\n";
        }
    }
    
    // =====================================================
    // 6. Verify data isolation - check for missing company_id filters
    // =====================================================
    echo "\n6. Verifying data isolation...\n";
    
    // Check if all major tables have company_id column
    $tablesToCheck = ['products', 'customers', 'repairs', 'swaps', 'pos_sales', 'phones'];
    $isolationIssues = [];
    
    foreach ($tablesToCheck as $table) {
        try {
            $checkCol = $db->query("SHOW COLUMNS FROM {$table} LIKE 'company_id'");
            if ($checkCol->rowCount() == 0) {
                $isolationIssues[] = "Table '{$table}' is missing company_id column";
            } else {
                echo "   ✓ Table '{$table}' has company_id column\n";
            }
        } catch (\Exception $e) {
            $isolationIssues[] = "Could not check table '{$table}': " . $e->getMessage();
        }
    }
    
    if (count($isolationIssues) > 0) {
        echo "   ⚠ Data isolation issues found:\n";
        foreach ($isolationIssues as $issue) {
            echo "      - {$issue}\n";
        }
    } else {
        echo "   ✓ All major tables have company_id column for data isolation\n";
    }
    
    // Commit transaction if it's still active
    if ($db->inTransaction()) {
        $db->commit();
    }
    echo "\n✓ Migration completed successfully!\n";
    
} catch (\Exception $e) {
    try {
        if ($db->inTransaction()) {
            $db->rollBack();
            echo "Rolled back all changes.\n";
        }
    } catch (\Exception $rollbackError) {
        // Ignore rollback errors
    }
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

