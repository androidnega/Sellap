-- Migration: Create subcategories and brands tables
-- Date: 2025-01-24

-- Create subcategories table
CREATE TABLE IF NOT EXISTS subcategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_subcategory_per_category (category_id, name)
);

-- Create brands table
CREATE TABLE IF NOT EXISTS brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    category_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    UNIQUE KEY unique_brand_per_category (name, category_id)
);

-- Add missing columns to products_new table if they don't exist
ALTER TABLE products_new 
ADD COLUMN IF NOT EXISTS subcategory_id INT NULL,
ADD COLUMN IF NOT EXISTS brand_id INT NULL,
ADD COLUMN IF NOT EXISTS sku VARCHAR(50) NULL UNIQUE,
ADD COLUMN IF NOT EXISTS model_name VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS description TEXT NULL,
ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS supplier VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS weight VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS dimensions VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS cost_price DECIMAL(12,2) NULL,
ADD COLUMN IF NOT EXISTS selling_price DECIMAL(12,2) NULL,
ADD COLUMN IF NOT EXISTS available_for_swap BOOLEAN DEFAULT FALSE;

-- Add foreign key constraints for products_new
ALTER TABLE products_new 
ADD CONSTRAINT IF NOT EXISTS fk_products_subcategory 
    FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_products_brand 
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_products_category ON products_new(category_id);
CREATE INDEX IF NOT EXISTS idx_products_subcategory ON products_new(subcategory_id);
CREATE INDEX IF NOT EXISTS idx_products_brand ON products_new(brand_id);
CREATE INDEX IF NOT EXISTS idx_products_sku ON products_new(sku);
