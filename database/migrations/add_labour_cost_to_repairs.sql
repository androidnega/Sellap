-- Add labour_cost column to repairs_new table
-- This tracks the actual cost of labour (what company pays technician or cost of providing service)
-- Profit from workmanship = repair_cost (revenue) - labour_cost (cost)

ALTER TABLE repairs_new 
ADD COLUMN IF NOT EXISTS labour_cost DECIMAL(10,2) DEFAULT 0 AFTER repair_cost;

-- Update existing repairs: set labour_cost to 50% of repair_cost as default
-- (This can be adjusted per repair later)
UPDATE repairs_new 
SET labour_cost = ROUND(repair_cost * 0.5, 2) 
WHERE labour_cost = 0 AND repair_cost > 0;

