-- =====================================================
-- Add Unique Constraint to Prevent Duplicate Customers
-- =====================================================
-- This migration adds a unique constraint to ensure that 
-- no two customers can have the same phone number within
-- the same company.
--
-- IMPORTANT: Run the cleanup_duplicate_customers.php script
-- BEFORE running this SQL migration to remove existing duplicates.
--
-- Usage:
-- mysql -u root -p sellapp_db < database/add_unique_constraint_customers.sql
-- =====================================================

USE sellapp_db;

-- Check if the constraint already exists
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'sellapp_db' 
    AND TABLE_NAME = 'customers' 
    AND CONSTRAINT_NAME = 'unique_company_phone'
);

-- Add unique constraint if it doesn't exist
-- This prevents duplicate phone numbers within the same company
SET @query = IF(
    @constraint_exists = 0,
    'ALTER TABLE customers ADD CONSTRAINT unique_company_phone UNIQUE (company_id, phone_number)',
    'SELECT "Constraint already exists" AS message'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the constraint was added
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    TABLE_NAME
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'sellapp_db'
AND TABLE_NAME = 'customers'
AND CONSTRAINT_NAME = 'unique_company_phone';

-- Display success message
SELECT 'Unique constraint added successfully!' AS status;
SELECT 'No two customers can now have the same phone number within the same company.' AS info;

