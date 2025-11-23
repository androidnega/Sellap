-- Migration: Seed laptop category and core brands
-- Date: 2025-11-24

-- Create laptops category if missing
INSERT INTO categories (name, description, is_active)
SELECT 'Laptops', 'Notebooks and portable computers', 1
WHERE NOT EXISTS (
    SELECT 1 FROM categories WHERE LOWER(name) = 'laptops'
);

-- Helper to fetch laptop category id
SET @laptop_category_id = (
    SELECT id FROM categories WHERE LOWER(name) = 'laptops' LIMIT 1
);

-- Insert default laptop brands
INSERT INTO brands (name, category_id)
SELECT 'Dell', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'dell' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'HP', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'hp' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Lenovo', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'lenovo' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Apple', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'apple' AND category_id = @laptop_category_id
);

