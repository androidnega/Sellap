-- =====================================================
-- CREATE SWAPPED ITEMS TABLE (Simple Version)
-- Run this SQL directly in your database
-- =====================================================

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

-- Add foreign key for swap_id (if swaps table exists)
-- If you get an error here, the swaps table may not exist yet
ALTER TABLE swapped_items 
ADD CONSTRAINT fk_swapped_items_swap 
FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE;

-- Note: If you get an error about inventory_product_id foreign key,
-- comment out the next line and uncomment the appropriate one based on your products table name
-- For products_new table:
-- ALTER TABLE swapped_items 
-- ADD CONSTRAINT fk_swapped_items_inventory_product 
-- FOREIGN KEY (inventory_product_id) REFERENCES products_new(id) ON DELETE SET NULL;

-- For products table:
-- ALTER TABLE swapped_items 
-- ADD CONSTRAINT fk_swapped_items_inventory_product 
-- FOREIGN KEY (inventory_product_id) REFERENCES products(id) ON DELETE SET NULL;

