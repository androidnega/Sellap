-- =====================================================
-- Comprehensive Inventory Management System Migration
-- Database: sellapp_db
-- Version: 3.0.0 - Enhanced Inventory Management
-- =====================================================

-- =====================================================
-- CATEGORIES TABLE (Product Categories)
-- =====================================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,    -- e.g., 'Phone', 'Accessory', 'Others'
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- BRANDS TABLE (Brands, mostly for phones)
-- =====================================================
CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,   -- e.g., 'Apple', 'Samsung'
    category_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_name (name),
    INDEX idx_category (category_id),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_brand_category (name, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ENHANCED PRODUCTS TABLE (All products added by manager)
-- =====================================================
CREATE TABLE IF NOT EXISTS products_new (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    brand_id INT NULL,                       -- only for phones
    specs JSON NULL,                         -- dynamic fields per brand
    price DECIMAL(10,2) NOT NULL,
    cost DECIMAL(10,2) DEFAULT 0,
    quantity INT DEFAULT 0,
    available_for_swap BOOLEAN DEFAULT FALSE,
    status ENUM('available','sold','swapped','out_of_stock') DEFAULT 'available',
    created_by INT NOT NULL,                 -- manager id (user_id)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_company (company_id),
    INDEX idx_category (category_id),
    INDEX idx_brand (brand_id),
    INDEX idx_status (status),
    INDEX idx_swap_available (available_for_swap),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ENHANCED REPAIRS TABLE (Repair bookings by technicians)
-- =====================================================
CREATE TABLE IF NOT EXISTS repairs_new (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    technician_id BIGINT UNSIGNED NOT NULL,
    product_id INT NULL,       -- optional, if repairing a phone from stock
    customer_name VARCHAR(100),
    customer_contact VARCHAR(50),
    customer_id BIGINT UNSIGNED NULL,  -- if customer exists in system
    issue_description TEXT,
    repair_cost DECIMAL(10,2) DEFAULT 0,
    parts_cost DECIMAL(10,2) DEFAULT 0,
    total_cost DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending','in_progress','completed','delivered','cancelled') DEFAULT 'pending',
    payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    tracking_code VARCHAR(20) UNIQUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (product_id) REFERENCES products_new(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_company (company_id),
    INDEX idx_technician (technician_id),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_tracking (tracking_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REPAIR ACCESSORIES TABLE (Accessories used per repair)
-- =====================================================
CREATE TABLE IF NOT EXISTS repair_accessories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repair_id INT NOT NULL,
    product_id INT NOT NULL,       -- only accessories
    quantity INT DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_id) REFERENCES repairs_new(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products_new(id) ON DELETE RESTRICT,
    INDEX idx_repair (repair_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CUSTOMER PRODUCTS TABLE (Products received from customers in swaps)
-- =====================================================
CREATE TABLE IF NOT EXISTS customer_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    brand VARCHAR(50),
    model VARCHAR(100),
    specs JSON,
    `condition` ENUM('new','used','faulty') DEFAULT 'used',
    estimated_value DECIMAL(10,2) DEFAULT 0,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('in_stock','sold','swapped') DEFAULT 'in_stock',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company (company_id),
    INDEX idx_status (status),
    INDEX idx_brand (brand),
    INDEX idx_condition (`condition`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ENHANCED SWAPS TABLE (Swaps linking store products to customer products)
-- =====================================================
CREATE TABLE IF NOT EXISTS swaps_new (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    store_product_id INT NOT NULL,        -- product from store
    customer_product_id INT NOT NULL,     -- product received from customer
    customer_name VARCHAR(100),
    customer_contact VARCHAR(50),
    customer_id BIGINT UNSIGNED NULL,     -- if customer exists in system
    swap_value DECIMAL(10,2),            -- value of customer product
    cash_added DECIMAL(10,2) DEFAULT 0,  -- extra cash customer pays
    total_value DECIMAL(10,2) NOT NULL,  -- total transaction value
    status ENUM('pending','completed','resold','cancelled') DEFAULT 'pending',
    created_by INT NOT NULL,             -- user who processed the swap
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (store_product_id) REFERENCES products_new(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_product_id) REFERENCES customer_products(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_company (company_id),
    INDEX idx_store_product (store_product_id),
    INDEX idx_customer_product (customer_product_id),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ENHANCED SALES TABLE (POS sales - normal, swap, repair)
-- =====================================================
CREATE TABLE IF NOT EXISTS sales_new (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    sale_type ENUM('normal','swap','repair') DEFAULT 'normal',
    customer_name VARCHAR(100) NULL,
    customer_contact VARCHAR(50) NULL,
    customer_id BIGINT UNSIGNED NULL,
    subtotal DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) DEFAULT 0,
    payment_method ENUM('cash','mobile_money','card','bank_transfer') DEFAULT 'cash',
    payment_status ENUM('paid','partial','unpaid') DEFAULT 'paid',
    cashier_id INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_company (company_id),
    INDEX idx_sale_type (sale_type),
    INDEX idx_cashier (cashier_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SALES ITEMS TABLE (Items sold in each sale)
-- =====================================================
CREATE TABLE IF NOT EXISTS sales_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NULL,         -- normal product or accessory
    swap_id INT NULL,            -- if item is swapped
    repair_id INT NULL,          -- if item is repair service
    item_type ENUM('product','swap','repair') DEFAULT 'product',
    item_description VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales_new(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products_new(id) ON DELETE SET NULL,
    FOREIGN KEY (swap_id) REFERENCES swaps_new(id) ON DELETE SET NULL,
    FOREIGN KEY (repair_id) REFERENCES repairs_new(id) ON DELETE SET NULL,
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id),
    INDEX idx_swap (swap_id),
    INDEX idx_repair (repair_id),
    INDEX idx_item_type (item_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT CATEGORIES
-- =====================================================
INSERT IGNORE INTO categories (name, description) VALUES
('Phone', 'Mobile phones and smartphones'),
('Accessory', 'Phone accessories like cases, chargers, etc.'),
('Others', 'Other products and services');

-- =====================================================
-- INSERT DEFAULT BRANDS
-- =====================================================
INSERT IGNORE INTO brands (name, category_id) VALUES
-- Phone brands
('Apple', (SELECT id FROM categories WHERE name = 'Phone')),
('Samsung', (SELECT id FROM categories WHERE name = 'Phone')),
('Huawei', (SELECT id FROM categories WHERE name = 'Phone')),
('Xiaomi', (SELECT id FROM categories WHERE name = 'Phone')),
('Oppo', (SELECT id FROM categories WHERE name = 'Phone')),
('Vivo', (SELECT id FROM categories WHERE name = 'Phone')),
('OnePlus', (SELECT id FROM categories WHERE name = 'Phone')),
('Google', (SELECT id FROM categories WHERE name = 'Phone')),
('Nokia', (SELECT id FROM categories WHERE name = 'Phone')),
('Tecno', (SELECT id FROM categories WHERE name = 'Phone')),
('Infinix', (SELECT id FROM categories WHERE name = 'Phone')),
('Itel', (SELECT id FROM categories WHERE name = 'Phone')),

-- Accessory brands
('Generic', (SELECT id FROM categories WHERE name = 'Accessory')),
('OEM', (SELECT id FROM categories WHERE name = 'Accessory')),
('Anker', (SELECT id FROM categories WHERE name = 'Accessory')),
('Belkin', (SELECT id FROM categories WHERE name = 'Accessory')),
('Spigen', (SELECT id FROM categories WHERE name = 'Accessory')),
('OtterBox', (SELECT id FROM categories WHERE name = 'Accessory'));

-- =====================================================
-- MIGRATION NOTES
-- =====================================================
-- This migration creates a comprehensive inventory management system that includes:
-- 1. Categories and Brands for better product organization
-- 2. Enhanced Products table with JSON specs for dynamic fields
-- 3. Complete Repair system with accessories tracking
-- 4. Customer Products for swap inventory
-- 5. Enhanced Swaps with better tracking
-- 6. Comprehensive Sales system supporting multiple sale types
-- 7. Sales Items for detailed transaction tracking
-- 
-- The system maintains multi-tenant architecture with company_id foreign keys
-- All tables include proper indexing for performance
-- Foreign key constraints ensure data integrity
-- JSON fields allow for flexible product specifications
