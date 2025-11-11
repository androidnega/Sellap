-- Fix company_id column type in sms_payments table if it exists and is wrong type
-- This handles the case where the table was created with INT instead of BIGINT UNSIGNED

-- Check if table exists and fix company_id type
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments');

SET @col_type = (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND COLUMN_NAME = 'company_id');

-- If table exists and company_id is not BIGINT UNSIGNED, fix it
SET @sql = IF(@table_exists > 0 AND @col_type != 'bigint(20) unsigned',
    'ALTER TABLE sms_payments MODIFY COLUMN company_id BIGINT UNSIGNED NOT NULL',
    'SELECT 1 AS company_id_already_correct_or_table_not_exists');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

