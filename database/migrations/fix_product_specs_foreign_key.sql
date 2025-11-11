-- Fix product_specs foreign key constraint
-- The product_specs table was referencing products(id) but should reference products_new(id)

-- Drop the existing foreign key constraint
ALTER TABLE product_specs DROP FOREIGN KEY product_specs_ibfk_1;

-- Add the correct foreign key constraint to reference products_new
ALTER TABLE product_specs 
ADD CONSTRAINT product_specs_ibfk_1 
FOREIGN KEY (product_id) REFERENCES products_new(id) ON DELETE CASCADE;
