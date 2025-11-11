-- Add accessory_cost field to repairs table
-- This field tracks the total cost of accessories used in the repair

ALTER TABLE repairs_new 
ADD COLUMN accessory_cost DECIMAL(10,2) DEFAULT 0 AFTER parts_cost;

-- Update the total_cost calculation to include accessory_cost
-- Note: This will be handled in the application logic
