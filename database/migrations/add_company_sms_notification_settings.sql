-- Add SMS notification settings columns to company_sms_accounts table
-- These settings allow managers to control which services use company SMS credits

-- Check and add sms_purchase_enabled column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'company_sms_accounts' 
    AND COLUMN_NAME = 'sms_purchase_enabled');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE company_sms_accounts ADD COLUMN sms_purchase_enabled BOOLEAN NOT NULL DEFAULT 1 COMMENT ''Enable SMS notifications for purchases (uses company SMS credits)''', 
    'SELECT ''Column sms_purchase_enabled already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add sms_repair_enabled column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'company_sms_accounts' 
    AND COLUMN_NAME = 'sms_repair_enabled');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE company_sms_accounts ADD COLUMN sms_repair_enabled BOOLEAN NOT NULL DEFAULT 1 COMMENT ''Enable SMS notifications for repairs (uses company SMS credits)''', 
    'SELECT ''Column sms_repair_enabled already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add sms_swap_enabled column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'company_sms_accounts' 
    AND COLUMN_NAME = 'sms_swap_enabled');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE company_sms_accounts ADD COLUMN sms_swap_enabled BOOLEAN NOT NULL DEFAULT 1 COMMENT ''Enable SMS notifications for swaps (uses company SMS credits)''', 
    'SELECT ''Column sms_swap_enabled already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add sms_payment_enabled column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'company_sms_accounts' 
    AND COLUMN_NAME = 'sms_payment_enabled');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE company_sms_accounts ADD COLUMN sms_payment_enabled BOOLEAN NOT NULL DEFAULT 1 COMMENT ''Enable SMS notifications for partial payments (uses company SMS credits)''', 
    'SELECT ''Column sms_payment_enabled already exists''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing records to have default values (if columns were just added)
UPDATE company_sms_accounts 
SET sms_purchase_enabled = COALESCE(sms_purchase_enabled, 1), 
    sms_repair_enabled = COALESCE(sms_repair_enabled, 1), 
    sms_swap_enabled = COALESCE(sms_swap_enabled, 1),
    sms_payment_enabled = COALESCE(sms_payment_enabled, 1);

