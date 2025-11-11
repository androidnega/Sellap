-- =====================================================
-- SWAP SYSTEM SIMPLE MIGRATION
-- Core tables for enhanced swap system
-- =====================================================

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

-- =====================================================
-- UPDATE CUSTOMER_PRODUCTS TABLE FOR SWAP SUPPORT
-- =====================================================
-- Add swap-related fields to customer_products table if they don't exist
ALTER TABLE customer_products 
ADD COLUMN IF NOT EXISTS swap_id INT NULL,
ADD COLUMN IF NOT EXISTS resell_price DECIMAL(10,2) NULL,
ADD INDEX IF NOT EXISTS idx_swap_id (swap_id);
