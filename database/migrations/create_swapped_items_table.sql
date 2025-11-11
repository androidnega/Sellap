-- =====================================================
-- CREATE SWAPPED ITEMS TABLE
-- This table tracks items received from customers in swaps
-- until they are resold
-- =====================================================

-- Check if table already exists before creating
CREATE TABLE IF NOT EXISTS swapped_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    swap_id INT NOT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    imei VARCHAR(20) NULL,
    `condition` VARCHAR(20) NOT NULL,
    estimated_value DECIMAL(10,2) NOT NULL,
    resell_price DECIMAL(10,2) NOT NULL,
    status ENUM('in_stock','sold') DEFAULT 'in_stock',
    resold_on DATETIME NULL,
    inventory_product_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_swap (swap_id),
    INDEX idx_status (status),
    INDEX idx_brand_model (brand, model),
    INDEX idx_imei (imei)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for swap_id (only if swaps table exists)
-- Check if swaps table exists and foreign key doesn't exist
SET @table_exists = (SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'swaps');

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.table_constraints 
    WHERE table_schema = DATABASE() 
    AND table_name = 'swapped_items' 
    AND constraint_name = 'fk_swapped_items_swap');

SET @sql = IF(@table_exists > 0 AND @fk_exists = 0,
    'ALTER TABLE swapped_items ADD CONSTRAINT fk_swapped_items_swap 
     FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE',
    'SELECT "Foreign key already exists or swaps table not found" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for inventory_product_id (check which products table exists)
-- Try products_new first, then products
SET @products_table = NULL;
SET @products_table = IF(
    (SELECT COUNT(*) FROM information_schema.tables 
     WHERE table_schema = DATABASE() AND table_name = 'products_new') > 0,
    'products_new',
    IF(
        (SELECT COUNT(*) FROM information_schema.tables 
         WHERE table_schema = DATABASE() AND table_name = 'products') > 0,
        'products',
        NULL
    )
);

SET @fk_inv_exists = (SELECT COUNT(*) FROM information_schema.table_constraints 
    WHERE table_schema = DATABASE() 
    AND table_name = 'swapped_items' 
    AND constraint_name = 'fk_swapped_items_inventory_product');

SET @sql2 = IF(@products_table IS NOT NULL AND @fk_inv_exists = 0,
    CONCAT('ALTER TABLE swapped_items ADD CONSTRAINT fk_swapped_items_inventory_product 
     FOREIGN KEY (inventory_product_id) REFERENCES ', @products_table, '(id) ON DELETE SET NULL'),
    'SELECT "Foreign key already exists or products table not found" AS message');

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

