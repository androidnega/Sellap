-- =====================================================
-- ADD SALE IDs TO SWAP PROFIT LINKS MIGRATION
-- Links swap profit calculations to actual POS sales
-- =====================================================

-- Check and add company_item_sale_id column
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'swap_profit_links' 
    AND COLUMN_NAME = 'company_item_sale_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE swap_profit_links ADD COLUMN company_item_sale_id BIGINT UNSIGNED NULL AFTER swap_id',
    'SELECT "company_item_sale_id column already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add customer_item_sale_id column
SET @col_exists2 = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'swap_profit_links' 
    AND COLUMN_NAME = 'customer_item_sale_id'
);

SET @sql2 = IF(@col_exists2 = 0,
    'ALTER TABLE swap_profit_links ADD COLUMN customer_item_sale_id BIGINT UNSIGNED NULL AFTER company_item_sale_id',
    'SELECT "customer_item_sale_id column already exists" AS message'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Add foreign key constraints (only if they don't exist)
SET @fk_exists1 = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'swap_profit_links' 
    AND CONSTRAINT_NAME = 'fk_company_item_sale'
);

SET @sql3 = IF(@fk_exists1 = 0,
    'ALTER TABLE swap_profit_links ADD CONSTRAINT fk_company_item_sale FOREIGN KEY (company_item_sale_id) REFERENCES pos_sales(id) ON DELETE SET NULL',
    'SELECT "fk_company_item_sale constraint already exists" AS message'
);

PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @fk_exists2 = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'swap_profit_links' 
    AND CONSTRAINT_NAME = 'fk_customer_item_sale'
);

SET @sql4 = IF(@fk_exists2 = 0,
    'ALTER TABLE swap_profit_links ADD CONSTRAINT fk_customer_item_sale FOREIGN KEY (customer_item_sale_id) REFERENCES pos_sales(id) ON DELETE SET NULL',
    'SELECT "fk_customer_item_sale constraint already exists" AS message'
);

PREPARE stmt4 FROM @sql4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

-- Add indexes (only if they don't exist)
SET @idx_exists1 = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'swap_profit_links' 
    AND INDEX_NAME = 'idx_company_item_sale'
);

SET @sql5 = IF(@idx_exists1 = 0,
    'CREATE INDEX idx_company_item_sale ON swap_profit_links(company_item_sale_id)',
    'SELECT "idx_company_item_sale index already exists" AS message'
);

PREPARE stmt5 FROM @sql5;
EXECUTE stmt5;
DEALLOCATE PREPARE stmt5;

SET @idx_exists2 = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'swap_profit_links' 
    AND INDEX_NAME = 'idx_customer_item_sale'
);

SET @sql6 = IF(@idx_exists2 = 0,
    'CREATE INDEX idx_customer_item_sale ON swap_profit_links(customer_item_sale_id)',
    'SELECT "idx_customer_item_sale index already exists" AS message'
);

PREPARE stmt6 FROM @sql6;
EXECUTE stmt6;
DEALLOCATE PREPARE stmt6;

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================

