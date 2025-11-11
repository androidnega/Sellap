-- Add product_id and shop_location fields to products_new table
-- Date: 2024-12-24

-- Add product_id field (unique identifier for each product)
ALTER TABLE products_new 
ADD COLUMN IF NOT EXISTS product_id VARCHAR(20) UNIQUE AFTER id;

-- Add item_location field (where the product is located in the shop)
ALTER TABLE products_new 
ADD COLUMN IF NOT EXISTS item_location VARCHAR(100) NULL AFTER quantity;

-- Add index for product_id for faster lookups
ALTER TABLE products_new 
ADD INDEX IF NOT EXISTS idx_product_id (product_id);

-- Add index for item_location for filtering
ALTER TABLE products_new 
ADD INDEX IF NOT EXISTS idx_item_location (item_location);

-- Generate product_id for existing products
UPDATE products_new 
SET product_id = CONCAT('PID-', LPAD(id, 3, '0'))
WHERE product_id IS NULL;
