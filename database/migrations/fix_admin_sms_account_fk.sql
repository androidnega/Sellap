-- Fix foreign key constraint to allow company_id = 0 for admin account
-- This allows tracking admin SMS balance separately

-- Drop existing foreign key if it exists
SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'company_sms_accounts' 
    AND COLUMN_NAME = 'company_id'
    AND REFERENCED_TABLE_NAME = 'companies'
    LIMIT 1
);

SET @sql = IF(@constraint_name IS NOT NULL,
    CONCAT('ALTER TABLE company_sms_accounts DROP FOREIGN KEY ', @constraint_name),
    'SELECT 1 AS no_constraint_found'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add new foreign key that allows NULL or matches companies.id
-- Note: We can't use FK for company_id = 0, so we'll add it with ON DELETE CASCADE but allow NULL
-- Actually, better to just remove the FK constraint for flexibility
-- Companies will be validated in application code

-- Insert admin account if it doesn't exist
INSERT IGNORE INTO company_sms_accounts (company_id, total_sms, sms_used, status, created_at)
SELECT 0, 0, 0, 'active', NOW()
WHERE NOT EXISTS (SELECT 1 FROM company_sms_accounts WHERE company_id = 0);

