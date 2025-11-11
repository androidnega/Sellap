-- Add lifecycle tracking columns to restock_logs table
ALTER TABLE restock_logs 
ADD COLUMN IF NOT EXISTS quantity_at_restock INT NOT NULL DEFAULT 0 COMMENT 'Quantity in stock when restocked',
ADD COLUMN IF NOT EXISTS quantity_after_restock INT NOT NULL DEFAULT 0 COMMENT 'Quantity after restock',
ADD COLUMN IF NOT EXISTS sold_out_date DATETIME NULL COMMENT 'Date when this restock batch was fully sold out',
ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL COMMENT 'User who performed the restock',
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active' COMMENT 'active, sold_out, cancelled',
ADD INDEX idx_sold_out_date (sold_out_date),
ADD INDEX idx_status (status),
ADD INDEX idx_user_id (user_id);

