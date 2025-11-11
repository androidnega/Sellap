-- Migration: Add device brand and model fields to repairs_new table
-- This allows technicians to record details when repairing customer's own device

ALTER TABLE repairs_new 
ADD COLUMN IF NOT EXISTS device_brand VARCHAR(100) NULL AFTER product_id,
ADD COLUMN IF NOT EXISTS device_model VARCHAR(100) NULL AFTER device_brand;

