-- =====================================================
-- Fix technician_id data type mismatch in repairs_new table
-- Issue: technician_id is INT but users.id is BIGINT UNSIGNED
-- This causes foreign key constraint violations
-- =====================================================

-- Drop the existing foreign key constraint first
ALTER TABLE repairs_new DROP FOREIGN KEY repairs_new_ibfk_2;

-- Modify technician_id column to match users.id data type (BIGINT UNSIGNED)
ALTER TABLE repairs_new MODIFY COLUMN technician_id BIGINT UNSIGNED NOT NULL;

-- Recreate the foreign key constraint with the correct data type
ALTER TABLE repairs_new 
ADD CONSTRAINT repairs_new_ibfk_2 
FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE RESTRICT;



