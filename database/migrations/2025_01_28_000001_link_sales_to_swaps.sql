-- =====================================================
-- LINK SALES TO SWAPS MIGRATION
-- Connect POS sales system with existing swap system
-- =====================================================

-- Add swap_id to pos_sales table
ALTER TABLE pos_sales 
ADD COLUMN IF NOT EXISTS swap_id INT NULL AFTER id,
ADD COLUMN IF NOT EXISTS is_swap_mode BOOLEAN DEFAULT FALSE AFTER swap_id;

-- Add foreign key constraint for swap_id
ALTER TABLE pos_sales 
ADD CONSTRAINT fk_pos_sales_swap_id 
FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE SET NULL;

-- Add swap_id to pos_sale_items table
ALTER TABLE pos_sale_items 
ADD COLUMN IF NOT EXISTS swap_id INT NULL AFTER pos_sale_id;

-- Add foreign key constraint for pos_sale_items swap_id
ALTER TABLE pos_sale_items 
ADD CONSTRAINT fk_pos_sale_items_swap_id 
FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE SET NULL;

-- Add swap_id to products_new table for resale products
ALTER TABLE products_new 
ADD COLUMN IF NOT EXISTS swap_id INT NULL AFTER linked_swap_id,
ADD COLUMN IF NOT EXISTS is_swap_item BOOLEAN DEFAULT FALSE AFTER swap_id;

-- Add foreign key constraint for products_new swap_id
ALTER TABLE products_new 
ADD CONSTRAINT fk_products_swap_id 
FOREIGN KEY (swap_id) REFERENCES swaps(id) ON DELETE SET NULL;

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_pos_sales_swap_id ON pos_sales(swap_id);
CREATE INDEX IF NOT EXISTS idx_pos_sale_items_swap_id ON pos_sale_items(swap_id);
CREATE INDEX IF NOT EXISTS idx_products_swap_id ON products_new(swap_id);
CREATE INDEX IF NOT EXISTS idx_products_is_swap_item ON products_new(is_swap_item);

-- Update payment_method enum to include 'swap' if needed
-- Note: This may require recreating the column depending on your MySQL version
-- ALTER TABLE pos_sales MODIFY COLUMN payment_method ENUM('cash','card','mobile_money','swap') DEFAULT 'cash';

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
