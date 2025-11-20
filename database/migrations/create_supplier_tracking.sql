-- =====================================================
-- SUPPLIER PRODUCT TRACKING TABLE
-- Tracks accumulated quantity and amount per supplier per product
-- This maintains tracking even when products go out of stock and are restocked
-- =====================================================
CREATE TABLE IF NOT EXISTS supplier_product_tracking (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    total_quantity_received INT DEFAULT 0, -- Total quantity ever received from this supplier for this product
    total_amount_spent DECIMAL(12, 2) DEFAULT 0.00, -- Total amount ever spent on this product from this supplier
    last_restock_quantity INT DEFAULT 0, -- Quantity added in last restock
    last_restock_amount DECIMAL(12, 2) DEFAULT 0.00, -- Amount spent in last restock
    last_restock_date TIMESTAMP NULL, -- Date of last restock from this supplier
    first_received_date TIMESTAMP NULL, -- Date when first received from this supplier
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_supplier_product_tracking (supplier_id, product_id),
    INDEX idx_company (company_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_product (product_id),
    INDEX idx_last_restock_date (last_restock_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add supplier_id column to products table if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'products';
SET @columnname = 'supplier_id';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' BIGINT UNSIGNED NULL, ADD INDEX idx_supplier_id (', @columnname, '), ADD FOREIGN KEY (', @columnname, ') REFERENCES suppliers(id) ON DELETE SET NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;


