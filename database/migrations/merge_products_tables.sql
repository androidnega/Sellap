-- =====================================================
-- Merge products and products_new tables into one
-- Consolidates all products into products_new structure
-- Date: 2025-01-28
-- 
-- IMPORTANT: BACKUP YOUR DATABASE BEFORE RUNNING THIS!
-- =====================================================

-- Step 1: Ensure products_new has all necessary columns
ALTER TABLE products_new 
ADD COLUMN IF NOT EXISTS sku VARCHAR(80) NULL,
ADD COLUMN IF NOT EXISTS subcategory_id INT NULL,
ADD COLUMN IF NOT EXISTS description TEXT NULL,
ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL;

-- Step 2: Migrate data from products to products_new
-- Map category names (VARCHAR) to category_ids (INT)
-- Map brand names (VARCHAR) to brand_ids (INT)
-- Convert qty to quantity, uppercase status to lowercase
INSERT INTO products_new (
    company_id,
    name,
    category_id,
    brand_id,
    price,
    cost,
    quantity,
    available_for_swap,
    status,
    created_by,
    sku,
    created_at,
    updated_at
)
SELECT 
    p.company_id,
    p.name,
    COALESCE(c.id, 1) as category_id,  -- Default to category id 1 if not found
    CASE 
        WHEN p.brand IS NOT NULL AND p.brand != '' THEN COALESCE(b.id, NULL)
        ELSE NULL
    END as brand_id,
    p.price,
    p.cost,
    COALESCE(p.qty, 0) as quantity,
    COALESCE(p.available_for_swap, 0) as available_for_swap,
    LOWER(p.status) as status,  -- Convert uppercase to lowercase
    COALESCE(p.created_by_user_id, 1) as created_by,
    p.sku,
    COALESCE(p.created_at, NOW()) as created_at,
    COALESCE(p.updated_at, NOW()) as updated_at
FROM products p
LEFT JOIN categories c ON LOWER(TRIM(c.name)) = LOWER(TRIM(p.category))
LEFT JOIN brands b ON LOWER(TRIM(b.name)) = LOWER(TRIM(p.brand)) 
    AND b.category_id = COALESCE(c.id, 1)
WHERE NOT EXISTS (
    -- Skip products that already exist in products_new (by name and company_id)
    SELECT 1 FROM products_new pn 
    WHERE pn.name = p.name 
    AND pn.company_id = p.company_id
)
AND p.company_id IS NOT NULL;

-- Step 3: Generate product_id for any products that don't have one
UPDATE products_new 
SET product_id = CONCAT('PID-', LPAD(id, 3, '0'))
WHERE product_id IS NULL OR product_id = '';

-- Step 4: Rename tables (products_new becomes products, old products becomes products_old_backup)
-- Backup old products table first
RENAME TABLE products TO products_old_backup;

-- Rename products_new to products (now the unified table)
RENAME TABLE products_new TO products;

-- =====================================================
-- Migration Complete
-- =====================================================
-- 
-- All code has been updated to use the unified 'products' table.
-- The old 'products' table has been renamed to 'products_old_backup'.
-- 
-- After verifying everything works correctly, you can drop the backup:
-- DROP TABLE IF EXISTS products_old_backup;
-- 
-- =====================================================
