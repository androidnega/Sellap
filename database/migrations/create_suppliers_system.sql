-- =====================================================
-- SUPPLIER MANAGEMENT SYSTEM MIGRATION
-- Database: sellapp_db
-- Version: 1.0.0 - Supplier Management
-- =====================================================

-- =====================================================
-- SUPPLIERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    alternate_phone VARCHAR(50) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    state VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    tax_id VARCHAR(100) NULL,
    payment_terms VARCHAR(255) NULL, -- e.g., "Net 30", "Cash on Delivery"
    credit_limit DECIMAL(12, 2) DEFAULT 0.00,
    notes TEXT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_company (company_id),
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PURCHASE ORDERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS purchase_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery_date DATE NULL,
    delivery_date DATE NULL,
    status ENUM('draft', 'pending', 'confirmed', 'partially_received', 'received', 'cancelled') DEFAULT 'draft',
    subtotal DECIMAL(12, 2) DEFAULT 0.00,
    tax_amount DECIMAL(12, 2) DEFAULT 0.00,
    shipping_cost DECIMAL(12, 2) DEFAULT 0.00,
    discount_amount DECIMAL(12, 2) DEFAULT 0.00,
    total_amount DECIMAL(12, 2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    payment_method VARCHAR(50) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_company (company_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_order_date (order_date),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PURCHASE ORDER ITEMS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL, -- Link to products table if product exists
    product_name VARCHAR(255) NOT NULL, -- Store product name for historical records
    product_sku VARCHAR(80) NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_cost DECIMAL(12, 2) NOT NULL,
    total_cost DECIMAL(12, 2) NOT NULL,
    quantity_received INT DEFAULT 0, -- Track received quantity
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_purchase_order (purchase_order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SUPPLIER PRODUCTS TABLE (Many-to-Many relationship)
-- Tracks which products are supplied by which suppliers
-- =====================================================
CREATE TABLE IF NOT EXISTS supplier_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    supplier_product_code VARCHAR(100) NULL, -- Supplier's product code/SKU
    unit_cost DECIMAL(12, 2) NULL, -- Last known cost from this supplier
    minimum_order_quantity INT DEFAULT 1,
    lead_time_days INT NULL, -- Days to deliver
    is_preferred TINYINT(1) DEFAULT 0, -- Is this the preferred supplier for this product?
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_supplier_product (supplier_id, product_id),
    INDEX idx_company (company_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_product (product_id),
    INDEX idx_preferred (is_preferred)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PURCHASE PAYMENTS TABLE (Track payments made to suppliers)
-- =====================================================
CREATE TABLE IF NOT EXISTS purchase_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL, -- cash, bank_transfer, check, etc.
    reference_number VARCHAR(100) NULL, -- Check number, transaction ID, etc.
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_company (company_id),
    INDEX idx_purchase_order (purchase_order_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

