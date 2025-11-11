-- =====================================================
-- Sample Products for Testing POS System
-- =====================================================

-- Insert sample categories first
INSERT INTO categories (id, name, description, created_at) 
VALUES 
    (1, 'Smartphones', 'Mobile phones and smartphones', NOW()),
    (2, 'Accessories', 'Phone accessories and peripherals', NOW()),
    (3, 'Repair Parts', 'Replacement parts for phone repairs', NOW())
ON DUPLICATE KEY UPDATE name=name;

-- Insert sample brands
INSERT INTO brands (id, name, description, created_at) 
VALUES 
    (1, 'Samsung', 'Samsung Electronics', NOW()),
    (2, 'Apple', 'Apple Inc.', NOW()),
    (3, 'Huawei', 'Huawei Technologies', NOW()),
    (4, 'Generic', 'Generic/Unbranded', NOW())
ON DUPLICATE KEY UPDATE name=name;

-- Insert sample products for Company 2 (PhoneHub Ltd)
INSERT INTO products_new (id, company_id, name, price, quantity, category_id, brand_id, status, created_by, created_at) 
VALUES 
    (1, 2, 'Samsung Galaxy S21', 2500.00, 5, 1, 1, 'available', 1, NOW()),
    (2, 2, 'iPhone 13', 3000.00, 3, 1, 2, 'available', 1, NOW()),
    (3, 2, 'Huawei P40 Pro', 2200.00, 4, 1, 3, 'available', 1, NOW()),
    (4, 2, 'Phone Case - Clear', 25.00, 20, 2, 4, 'available', 1, NOW()),
    (5, 2, 'Screen Protector', 15.00, 30, 2, 4, 'available', 1, NOW()),
    (6, 2, 'Charging Cable', 35.00, 25, 2, 4, 'available', 1, NOW()),
    (7, 2, 'Power Bank 10000mAh', 80.00, 10, 2, 4, 'available', 1, NOW()),
    (8, 2, 'Bluetooth Headphones', 120.00, 8, 2, 4, 'available', 1, NOW()),
    (9, 2, 'Phone Screen Replacement', 150.00, 0, 3, 4, 'available', 1, NOW()),
    (10, 2, 'Battery Replacement', 80.00, 0, 3, 4, 'available', 1, NOW())
ON DUPLICATE KEY UPDATE name=name;

-- =====================================================
-- Sample Products Added:
-- =====================================================
-- Company 2 (PhoneHub Ltd) now has:
-- - 3 Smartphones (Samsung Galaxy S21, iPhone 13, Huawei P40 Pro)
-- - 5 Accessories (Cases, Screen Protectors, Cables, Power Banks, Headphones)
-- - 2 Services (Screen Replacement, Battery Replacement)
-- 
-- Total: 10 products available for POS sales
-- =====================================================
