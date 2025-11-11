-- Update sms_payments table to support Paystack and vendor plans
-- Note: Some columns may already exist, so we'll check first or use IF NOT EXISTS equivalent logic

-- Modify payment_provider enum if it exists
ALTER TABLE sms_payments 
    MODIFY COLUMN payment_provider ENUM('paypal', 'stripe', 'manual', 'paystack') NOT NULL DEFAULT 'paystack';

-- Add vendor_plan_id if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND COLUMN_NAME = 'vendor_plan_id');
    
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE sms_payments ADD COLUMN vendor_plan_id INT NULL AFTER payment_provider',
    'SELECT 1 AS vendor_plan_id_exists');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add paystack_reference if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND COLUMN_NAME = 'paystack_reference');
    
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE sms_payments ADD COLUMN paystack_reference VARCHAR(128) NULL AFTER paypal_order_id',
    'SELECT 1 AS paystack_reference_exists');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add user_id if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND COLUMN_NAME = 'user_id');
    
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE sms_payments ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER company_id',
    'SELECT 1 AS user_id_exists');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add company_price if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND COLUMN_NAME = 'company_price');
    
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE sms_payments ADD COLUMN company_price DECIMAL(12,2) NULL AFTER amount',
    'SELECT 1 AS company_price_exists');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Modify status enum if column exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND COLUMN_NAME = 'status');
    
SET @sql = IF(@col_exists > 0,
    'ALTER TABLE sms_payments MODIFY COLUMN status ENUM(\'initiated\', \'pending\', \'success\', \'completed\', \'failed\', \'cancelled\', \'refunded\') NOT NULL DEFAULT \'initiated\'',
    'SELECT 1 AS status_column_not_found');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes if they don't exist
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND INDEX_NAME = 'idx_vendor_plan_id');
    
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE sms_payments ADD INDEX idx_vendor_plan_id (vendor_plan_id)',
    'SELECT 1 AS idx_vendor_plan_id_exists');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND INDEX_NAME = 'idx_paystack_reference');
    
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE sms_payments ADD INDEX idx_paystack_reference (paystack_reference)',
    'SELECT 1 AS idx_paystack_reference_exists');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND INDEX_NAME = 'idx_user_id');
    
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE sms_payments ADD INDEX idx_user_id (user_id)',
    'SELECT 1 AS idx_user_id_exists');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for vendor_plan_id if column exists and FK doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND COLUMN_NAME = 'vendor_plan_id');
    
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sms_payments' 
    AND CONSTRAINT_NAME = 'fk_sms_payments_vendor_plan');

SET @sql = IF(@col_exists > 0 AND @fk_exists = 0,
    'ALTER TABLE sms_payments ADD CONSTRAINT fk_sms_payments_vendor_plan FOREIGN KEY (vendor_plan_id) REFERENCES sms_vendor_plans(id) ON DELETE SET NULL',
    'SELECT 1 AS fk_skip');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

