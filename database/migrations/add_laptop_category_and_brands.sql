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

-- Additional popular laptop brands
INSERT INTO brands (name, category_id)
SELECT 'Acer', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'acer' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'ASUS', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'asus' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'MSI', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'msi' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Samsung', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'samsung' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Microsoft', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'microsoft' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Razer', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'razer' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Toshiba', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'toshiba' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'LG', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'lg' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Alienware', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'alienware' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Acer Predator', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'acer predator' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'HP Omen', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'hp omen' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Dell Alienware', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'dell alienware' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Chromebook', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'chromebook' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Huawei', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'huawei' AND category_id = @laptop_category_id
);

INSERT INTO brands (name, category_id)
SELECT 'Xiaomi', @laptop_category_id
WHERE @laptop_category_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM brands 
    WHERE LOWER(name) = 'xiaomi' AND category_id = @laptop_category_id
);

