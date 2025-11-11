-- =====================================================
-- Phase 7: Multi-Tenant Migration Script
-- SellApp - Company-Based Multi-Tenant Architecture
-- =====================================================
-- This script migrates existing SellApp database to multi-tenant architecture
-- Run this ONLY if you have an existing database with old schema
-- For fresh installs, use schema.sql instead

-- =====================================================
-- STEP 1: Create Companies Table
-- =====================================================
CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone_number VARCHAR(50),
    address TEXT,
    created_by_user_id BIGINT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_created_by (created_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STEP 2: Backup Existing Data (Optional but Recommended)
-- =====================================================
-- CREATE TABLE users_backup AS SELECT * FROM users;
-- CREATE TABLE customers_backup AS SELECT * FROM customers;
-- CREATE TABLE phones_backup AS SELECT * FROM phones;
-- CREATE TABLE swaps_backup AS SELECT * FROM swaps;
-- CREATE TABLE repairs_backup AS SELECT * FROM repairs;
-- CREATE TABLE pos_sales_backup AS SELECT * FROM pos_sales;

-- =====================================================
-- STEP 3: Modify Users Table
-- =====================================================

-- Change ID type to BIGINT UNSIGNED
ALTER TABLE users MODIFY COLUMN id BIGINT UNSIGNED AUTO_INCREMENT;

-- Add company_id column (temporarily allow NULL for migration)
ALTER TABLE users ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id;

-- Update role enum to new role names
ALTER TABLE users MODIFY COLUMN role ENUM('system_admin', 'manager', 'salesperson', 'technician') DEFAULT 'salesperson';

-- Update existing roles to new names
UPDATE users SET role = 'system_admin' WHERE role = 'super_admin';
UPDATE users SET role = 'salesperson' WHERE role = 'shop_keeper';
UPDATE users SET role = 'technician' WHERE role = 'repairer';

-- Add foreign key constraint for company_id
ALTER TABLE users ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

-- Add company index
ALTER TABLE users ADD INDEX idx_company (company_id);

-- =====================================================
-- STEP 4: Create Default Company for Existing Data
-- =====================================================
-- Insert a default company for existing users
INSERT INTO companies (name, email, phone_number, address, created_by_user_id)
VALUES ('Default Company', 'admin@sellapp.com', '+233000000000', 'Default Address', 1);

-- Set the company_id for all existing users (except system_admin)
UPDATE users SET company_id = (SELECT id FROM companies WHERE name = 'Default Company' LIMIT 1)
WHERE role != 'system_admin';

-- System admins should have NULL company_id
UPDATE users SET company_id = NULL WHERE role = 'system_admin';

-- =====================================================
-- STEP 5: Modify Customers Table
-- =====================================================
ALTER TABLE customers MODIFY COLUMN id BIGINT UNSIGNED AUTO_INCREMENT;
ALTER TABLE customers MODIFY COLUMN created_by_user_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE customers ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id;

-- Set company_id for existing customers based on their creator's company
UPDATE customers c
JOIN users u ON c.created_by_user_id = u.id
SET c.company_id = COALESCE(u.company_id, (SELECT id FROM companies WHERE name = 'Default Company' LIMIT 1));

-- Add foreign key and index
ALTER TABLE customers ADD CONSTRAINT fk_customers_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;
ALTER TABLE customers ADD INDEX idx_company (company_id);

-- =====================================================
-- STEP 6: Modify Phones Table
-- =====================================================
ALTER TABLE phones MODIFY COLUMN id BIGINT UNSIGNED AUTO_INCREMENT;
ALTER TABLE phones ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id;

-- Set company_id for existing phones (use default company)
UPDATE phones SET company_id = (SELECT id FROM companies WHERE name = 'Default Company' LIMIT 1);

-- Add foreign key and index
ALTER TABLE phones ADD CONSTRAINT fk_phones_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;
ALTER TABLE phones ADD INDEX idx_company (company_id);

-- =====================================================
-- STEP 7: Modify Swaps Table
-- =====================================================
ALTER TABLE swaps MODIFY COLUMN id BIGINT UNSIGNED AUTO_INCREMENT;
ALTER TABLE swaps MODIFY COLUMN customer_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE swaps MODIFY COLUMN new_phone_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE swaps MODIFY COLUMN created_by_user_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE swaps ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id;

-- Set company_id for existing swaps
UPDATE swaps s
JOIN users u ON s.created_by_user_id = u.id
SET s.company_id = COALESCE(u.company_id, (SELECT id FROM companies WHERE name = 'Default Company' LIMIT 1));

-- Add foreign key and index
ALTER TABLE swaps ADD CONSTRAINT fk_swaps_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;
ALTER TABLE swaps ADD INDEX idx_company (company_id);

-- =====================================================
-- STEP 8: Modify Repairs Table
-- =====================================================
ALTER TABLE repairs MODIFY COLUMN id BIGINT UNSIGNED AUTO_INCREMENT;
ALTER TABLE repairs MODIFY COLUMN customer_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE repairs MODIFY COLUMN created_by_user_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE repairs ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id;

-- Set company_id for existing repairs
UPDATE repairs r
JOIN users u ON r.created_by_user_id = u.id
SET r.company_id = COALESCE(u.company_id, (SELECT id FROM companies WHERE name = 'Default Company' LIMIT 1));

-- Add foreign key and index
ALTER TABLE repairs ADD CONSTRAINT fk_repairs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;
ALTER TABLE repairs ADD INDEX idx_company (company_id);

-- =====================================================
-- STEP 9: Modify POS Sales Table
-- =====================================================
ALTER TABLE pos_sales MODIFY COLUMN id BIGINT UNSIGNED AUTO_INCREMENT;
ALTER TABLE pos_sales MODIFY COLUMN customer_id BIGINT UNSIGNED;
ALTER TABLE pos_sales MODIFY COLUMN created_by_user_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE pos_sales ADD COLUMN company_id BIGINT UNSIGNED NOT NULL AFTER id;

-- Set company_id for existing sales
UPDATE pos_sales p
JOIN users u ON p.created_by_user_id = u.id
SET p.company_id = COALESCE(u.company_id, (SELECT id FROM companies WHERE name = 'Default Company' LIMIT 1));

-- Add foreign key and index
ALTER TABLE pos_sales ADD CONSTRAINT fk_pos_sales_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;
ALTER TABLE pos_sales ADD INDEX idx_company (company_id);

-- =====================================================
-- STEP 10: Modify POS Sale Items Table
-- =====================================================
ALTER TABLE pos_sale_items MODIFY COLUMN id BIGINT UNSIGNED AUTO_INCREMENT;
ALTER TABLE pos_sale_items MODIFY COLUMN pos_sale_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE pos_sale_items MODIFY COLUMN item_id BIGINT UNSIGNED;

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
-- Verify the migration with these queries:

-- Check companies
-- SELECT * FROM companies;

-- Check users with company assignments
-- SELECT id, username, role, company_id FROM users;

-- Check data integrity
-- SELECT 'Customers', COUNT(*) FROM customers UNION
-- SELECT 'Phones', COUNT(*) FROM phones UNION
-- SELECT 'Swaps', COUNT(*) FROM swaps UNION
-- SELECT 'Repairs', COUNT(*) FROM repairs UNION
-- SELECT 'POS Sales', COUNT(*) FROM pos_sales;

-- =====================================================
-- ROLLBACK INSTRUCTIONS (If needed)
-- =====================================================
-- If you need to rollback this migration:
-- 1. Restore from backups created in Step 2
-- 2. Or run these commands:
--
-- ALTER TABLE users DROP FOREIGN KEY fk_users_company;
-- ALTER TABLE users DROP COLUMN company_id;
-- ALTER TABLE customers DROP FOREIGN KEY fk_customers_company;
-- ALTER TABLE customers DROP COLUMN company_id;
-- ALTER TABLE phones DROP FOREIGN KEY fk_phones_company;
-- ALTER TABLE phones DROP COLUMN company_id;
-- ALTER TABLE swaps DROP FOREIGN KEY fk_swaps_company;
-- ALTER TABLE swaps DROP COLUMN company_id;
-- ALTER TABLE repairs DROP FOREIGN KEY fk_repairs_company;
-- ALTER TABLE repairs DROP COLUMN company_id;
-- ALTER TABLE pos_sales DROP FOREIGN KEY fk_pos_sales_company;
-- ALTER TABLE pos_sales DROP COLUMN company_id;
-- DROP TABLE companies;
-- 
-- Then update roles back:
-- UPDATE users SET role = 'super_admin' WHERE role = 'system_admin';
-- UPDATE users SET role = 'shop_keeper' WHERE role = 'salesperson';
-- UPDATE users SET role = 'repairer' WHERE role = 'technician';



