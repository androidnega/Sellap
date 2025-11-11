-- =====================================================
-- SWAP SYSTEM ENHANCEMENT MIGRATION
-- Enhanced swap system with profit tracking and inventory integration
-- =====================================================

-- Drop existing swap tables if they exist (for fresh install)
-- DROP TABLE IF EXISTS swap_profit_links;
-- DROP TABLE IF EXISTS swapped_items;
-- DROP TABLE IF EXISTS swaps;

-- =====================================================
-- ENHANCED SWAPS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS swaps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(50) UNIQUE NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    company_product_id INT NOT NULL,
    customer_product_id INT NULL,
    added_cash DECIMAL(10,2) DEFAULT 0,
    difference_paid_by_company DECIMAL(10,2) DEFAULT 0,
    total_value DECIMAL(10,2) NOT NULL,
    swap_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    handled_by INT NOT NULL,
    status ENUM('pending','completed','resold') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (company_product_id) REFERENCES products_new(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_product_id) REFERENCES customer_products(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_company (company_id),
    INDEX idx_transaction_code (transaction_code),
    INDEX idx_company_product (company_product_id),
    INDEX idx_customer_product (customer_product_id),
    INDEX idx_status (status),
    INDEX idx_handled_by (handled_by),
    INDEX idx_swap_date (swap_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SWAPPED ITEMS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS swapped_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    swap_id INT NOT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    imei VARCHAR(20) NULL,
    condition VARCHAR(20) NOT NULL,
    estimated_value DECIMAL(10,2) NOT NULL,
    resell_price DECIMAL(10,2) NOT NULL,
    status ENUM('in_stock','sold') DEFAULT 'in_stock',
    resold_on DATETIME NULL,
    inventory_product_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE,
    FOREIGN KEY (inventory_product_id) REFERENCES products_new(id) ON DELETE SET NULL,
    INDEX idx_swap (swap_id),
    INDEX idx_status (status),
    INDEX idx_brand_model (brand, model),
    INDEX idx_imei (imei)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SWAP PROFIT LINKS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS swap_profit_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    swap_id INT NOT NULL,
    company_product_cost DECIMAL(10,2) NOT NULL,
    customer_phone_value DECIMAL(10,2) NOT NULL,
    amount_added_by_customer DECIMAL(10,2) DEFAULT 0,
    profit_estimate DECIMAL(10,2) NOT NULL,
    final_profit DECIMAL(10,2) NULL,
    status ENUM('pending','finalized') DEFAULT 'pending',
    finalized_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE CASCADE,
    INDEX idx_swap (swap_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- UPDATE PRODUCTS TABLE FOR SWAP SUPPORT
-- =====================================================
-- Add swap-related fields to products_new table if they don't exist
ALTER TABLE products_new 
ADD COLUMN IF NOT EXISTS available_for_swap TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS source ENUM('purchase','swap','repair') DEFAULT 'purchase',
ADD COLUMN IF NOT EXISTS linked_swap_id INT NULL,
ADD INDEX IF NOT EXISTS idx_available_for_swap (available_for_swap),
ADD INDEX IF NOT EXISTS idx_source (source),
ADD INDEX IF NOT EXISTS idx_linked_swap (linked_swap_id);

-- Add foreign key constraint for linked_swap_id if it doesn't exist
-- ALTER TABLE products_new 
-- ADD CONSTRAINT fk_products_linked_swap 
-- FOREIGN KEY (linked_swap_id) REFERENCES swaps(id) ON DELETE SET NULL;

-- =====================================================
-- UPDATE CUSTOMER_PRODUCTS TABLE FOR SWAP SUPPORT
-- =====================================================
-- Add swap-related fields to customer_products table if they don't exist
ALTER TABLE customer_products 
ADD COLUMN IF NOT EXISTS swap_id INT NULL,
ADD COLUMN IF NOT EXISTS resell_price DECIMAL(10,2) NULL,
ADD INDEX IF NOT EXISTS idx_swap_id (swap_id);

-- Add foreign key constraint for swap_id if it doesn't exist
-- ALTER TABLE customer_products 
-- ADD CONSTRAINT fk_customer_products_swap 
-- FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE SET NULL;

-- =====================================================
-- INSERT SAMPLE DATA (Optional - for testing)
-- =====================================================
-- Uncomment the following lines to insert sample data for testing

/*
-- Sample swap transaction
INSERT INTO swaps (
    transaction_code, company_id, customer_name, customer_phone, 
    company_product_id, added_cash, total_value, handled_by, status
) VALUES (
    'SWP-2025001', 1, 'John Doe', '+233123456789', 
    1, 500.00, 1500.00, 1, 'completed'
);

-- Sample swapped item
INSERT INTO swapped_items (
    swap_id, brand, model, condition, estimated_value, resell_price
) VALUES (
    1, 'Samsung', 'Galaxy S21', 'Good', 1000.00, 1200.00
);

-- Sample profit link
INSERT INTO swap_profit_links (
    swap_id, company_product_cost, customer_phone_value, 
    amount_added_by_customer, profit_estimate
) VALUES (
    1, 800.00, 1000.00, 500.00, 700.00
);
*/

-- =====================================================
-- CREATE VIEWS FOR SWAP DASHBOARD
-- =====================================================

-- View for active swaps (pending resale)
CREATE OR REPLACE VIEW active_swaps AS
SELECT 
    s.id,
    s.transaction_code,
    s.customer_name,
    s.customer_phone,
    s.swap_date,
    s.status,
    u.full_name as handled_by_name,
    sp.name as company_product_name,
    sp.price as company_product_price,
    si.brand as customer_product_brand,
    si.model as customer_product_model,
    si.estimated_value as customer_product_value,
    si.resell_price,
    si.status as resale_status,
    s.added_cash,
    s.total_value
FROM swaps s
LEFT JOIN users u ON s.handled_by = u.id
LEFT JOIN products_new sp ON s.company_product_id = sp.id
LEFT JOIN swapped_items si ON s.id = si.swap_id
WHERE s.status IN ('completed', 'resold')
ORDER BY s.swap_date DESC;

-- View for swap profit tracking
CREATE OR REPLACE VIEW swap_profit_tracking AS
SELECT 
    s.id,
    s.transaction_code,
    s.customer_name,
    s.swap_date,
    sp.name as company_product_name,
    sp.price as company_product_selling_price,
    si.brand as customer_product_brand,
    si.model as customer_product_model,
    si.resell_price as customer_product_resell_price,
    spl.company_product_cost,
    spl.customer_phone_value,
    spl.amount_added_by_customer,
    spl.profit_estimate,
    spl.final_profit,
    spl.status as profit_status,
    CASE 
        WHEN si.status = 'sold' THEN 'Finalized'
        ELSE 'Pending Resale'
    END as overall_status
FROM swaps s
LEFT JOIN products_new sp ON s.company_product_id = sp.id
LEFT JOIN swapped_items si ON s.id = si.swap_id
LEFT JOIN swap_profit_links spl ON s.id = spl.swap_id
WHERE s.status IN ('completed', 'resold')
ORDER BY s.swap_date DESC;

-- =====================================================
-- CREATE STORED PROCEDURES FOR SWAP OPERATIONS
-- =====================================================

DELIMITER //

-- Procedure to complete a swap transaction
CREATE PROCEDURE IF NOT EXISTS CompleteSwap(
    IN p_company_id BIGINT,
    IN p_customer_name VARCHAR(100),
    IN p_customer_phone VARCHAR(50),
    IN p_customer_id BIGINT,
    IN p_company_product_id INT,
    IN p_customer_brand VARCHAR(50),
    IN p_customer_model VARCHAR(100),
    IN p_customer_imei VARCHAR(20),
    IN p_customer_condition VARCHAR(20),
    IN p_estimated_value DECIMAL(10,2),
    IN p_resell_price DECIMAL(10,2),
    IN p_added_cash DECIMAL(10,2),
    IN p_handled_by INT,
    IN p_notes TEXT,
    OUT p_swap_id INT,
    OUT p_transaction_code VARCHAR(50)
)
BEGIN
    DECLARE v_total_value DECIMAL(10,2);
    DECLARE v_company_product_price DECIMAL(10,2);
    DECLARE v_transaction_code VARCHAR(50);
    DECLARE v_swap_id INT;
    DECLARE v_customer_product_id INT;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Generate transaction code
    SET v_transaction_code = CONCAT('SWP-', YEAR(NOW()), LPAD(DAYOFYEAR(NOW()), 3, '0'), LPAD(LAST_INSERT_ID() + 1, 3, '0'));
    
    -- Get company product price
    SELECT price INTO v_company_product_price 
    FROM products_new 
    WHERE id = p_company_product_id AND company_id = p_company_id;
    
    -- Calculate total value
    SET v_total_value = v_company_product_price - p_estimated_value + p_added_cash;
    
    -- Create customer product record
    INSERT INTO customer_products (
        company_id, brand, model, condition, estimated_value, 
        resell_price, status, notes
    ) VALUES (
        p_company_id, p_customer_brand, p_customer_model, p_customer_condition,
        p_estimated_value, p_resell_price, 'in_stock', 'Received in swap'
    );
    
    SET v_customer_product_id = LAST_INSERT_ID();
    
    -- Create swap record
    INSERT INTO swaps (
        transaction_code, company_id, customer_name, customer_phone, customer_id,
        company_product_id, customer_product_id, added_cash, total_value,
        handled_by, status, notes
    ) VALUES (
        v_transaction_code, p_company_id, p_customer_name, p_customer_phone, p_customer_id,
        p_company_product_id, v_customer_product_id, p_added_cash, v_total_value,
        p_handled_by, 'completed', p_notes
    );
    
    SET v_swap_id = LAST_INSERT_ID();
    
    -- Create swapped item record
    INSERT INTO swapped_items (
        swap_id, brand, model, imei, condition, estimated_value, resell_price
    ) VALUES (
        v_swap_id, p_customer_brand, p_customer_model, p_customer_imei,
        p_customer_condition, p_estimated_value, p_resell_price
    );
    
    -- Create profit link record
    INSERT INTO swap_profit_links (
        swap_id, company_product_cost, customer_phone_value, 
        amount_added_by_customer, profit_estimate
    ) VALUES (
        v_swap_id, v_company_product_price * 0.7, p_estimated_value, 
        p_added_cash, (v_company_product_price + p_resell_price) - (v_company_product_price * 0.7 + p_estimated_value)
    );
    
    -- Update customer product with swap reference
    UPDATE customer_products SET swap_id = v_swap_id WHERE id = v_customer_product_id;
    
    -- Update company product quantity
    UPDATE products_new SET quantity = quantity - 1 WHERE id = p_company_product_id;
    
    -- Commit transaction
    COMMIT;
    
    -- Set output parameters
    SET p_swap_id = v_swap_id;
    SET p_transaction_code = v_transaction_code;
    
END //

-- Procedure to finalize swap profit when customer product is resold
CREATE PROCEDURE IF NOT EXISTS FinalizeSwapProfit(
    IN p_swap_id INT,
    IN p_actual_resell_price DECIMAL(10,2)
)
BEGIN
    DECLARE v_company_product_cost DECIMAL(10,2);
    DECLARE v_customer_phone_value DECIMAL(10,2);
    DECLARE v_amount_added_by_customer DECIMAL(10,2);
    DECLARE v_final_profit DECIMAL(10,2);
    
    -- Start transaction
    START TRANSACTION;
    
    -- Get profit link data
    SELECT company_product_cost, customer_phone_value, amount_added_by_customer
    INTO v_company_product_cost, v_customer_phone_value, v_amount_added_by_customer
    FROM swap_profit_links
    WHERE swap_id = p_swap_id;
    
    -- Calculate final profit
    SET v_final_profit = p_actual_resell_price - v_customer_phone_value;
    
    -- Update profit link
    UPDATE swap_profit_links 
    SET final_profit = v_final_profit, 
        status = 'finalized', 
        finalized_at = NOW()
    WHERE swap_id = p_swap_id;
    
    -- Update swapped item status
    UPDATE swapped_items 
    SET status = 'sold', resold_on = NOW()
    WHERE swap_id = p_swap_id;
    
    -- Update swap status
    UPDATE swaps 
    SET status = 'resold'
    WHERE id = p_swap_id;
    
    -- Commit transaction
    COMMIT;
    
END //

DELIMITER ;

-- =====================================================
-- CREATE TRIGGERS FOR AUTOMATIC UPDATES
-- =====================================================

-- Trigger to update swap status when customer product is sold
DELIMITER //

CREATE TRIGGER IF NOT EXISTS tr_customer_product_sold
AFTER UPDATE ON customer_products
FOR EACH ROW
BEGIN
    IF NEW.status = 'sold' AND OLD.status != 'sold' AND NEW.swap_id IS NOT NULL THEN
        CALL FinalizeSwapProfit(NEW.swap_id, NEW.resell_price);
    END IF;
END //

DELIMITER ;

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
