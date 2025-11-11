-- Product System Migration
-- Unified product catalog for phones, accessories, and repair parts
-- Multi-tenant aware with company scoping

-- Products table: unified catalog per company
CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NULL,
  sku VARCHAR(80) UNIQUE,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(50) NOT NULL, -- phone, accessory, repair_part, etc.
  brand VARCHAR(100) NULL,
  price DECIMAL(12,2) DEFAULT 0.00,
  cost DECIMAL(12,2) DEFAULT 0.00,
  qty INT DEFAULT 0,
  available_for_swap TINYINT(1) DEFAULT 0,
  status ENUM('AVAILABLE','SOLD','OUT_OF_STOCK') DEFAULT 'AVAILABLE',
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_company_category (company_id, category),
  INDEX idx_company_status (company_id, status),
  INDEX idx_sku (sku),
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Product specifications: flexible key-value storage
CREATE TABLE IF NOT EXISTS product_specs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  spec_key VARCHAR(120) NOT NULL,
  spec_value VARCHAR(300) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_product_specs (product_id, spec_key),
  INDEX idx_spec_key (spec_key),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Product images: optional image storage
CREATE TABLE IF NOT EXISTS product_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  alt_text VARCHAR(255),
  is_primary TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_product_images (product_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Product categories: predefined categories
CREATE TABLE IF NOT EXISTS product_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) UNIQUE NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default product categories
INSERT IGNORE INTO product_categories (slug, name, description) VALUES
('phone', 'Phone', 'Mobile phones and smartphones'),
('accessory', 'Accessory', 'Phone accessories like cases, chargers, etc.'),
('repair_part', 'Repair Part', 'Parts used for phone repairs');

-- Add unique constraint for IMEI if needed (uncomment if enforcing unique IMEI per product)
-- ALTER TABLE product_specs ADD UNIQUE KEY unique_imei (spec_value) WHERE spec_key = 'imei';

-- Sample data for testing (optional - remove in production)
-- INSERT INTO products (company_id, sku, name, category, brand, price, cost, qty, available_for_swap, status) VALUES
-- (1, 'IPHONE-12-128', 'iPhone 12 128GB', 'phone', 'Apple', 2500.00, 2000.00, 5, 1, 'AVAILABLE'),
-- (1, 'CASE-UNIV', 'Universal Phone Case', 'accessory', 'Generic', 25.00, 15.00, 50, 0, 'AVAILABLE'),
-- (1, 'SCREEN-IPHONE12', 'iPhone 12 Screen', 'repair_part', 'Apple', 150.00, 100.00, 10, 0, 'AVAILABLE');
