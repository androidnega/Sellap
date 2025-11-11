-- =====================================================
-- Sample Company and Manager for Testing
-- =====================================================

-- Insert a sample company (will be ID 1 if it's the first)
INSERT INTO companies (id, name, email, phone_number, address, created_by_user_id, created_at) 
VALUES 
    (1, 'TechMobile Ghana', 'info@techmobile.gh', '+233244123456', 'Accra, Ghana', 1, NOW()),
    (2, 'PhoneHub Ltd', 'contact@phonehub.com', '+233501234567', 'Kumasi, Ghana', 1, NOW())
ON DUPLICATE KEY UPDATE name=name;

-- Insert sample managers for these companies
-- Password for all: manager123 (bcrypt hashed)
INSERT INTO users (company_id, unique_id, username, email, phone_number, full_name, password, role, is_active) 
VALUES 
    (1, 'USRMGR001', 'manager1', 'manager@techmobile.gh', '+233244123456', 'John Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1),
    (2, 'USRMGR002', 'manager2', 'manager@phonehub.com', '+233501234567', 'Jane Manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1)
ON DUPLICATE KEY UPDATE username=username;

-- Insert some sample staff for testing
INSERT INTO users (company_id, unique_id, username, email, phone_number, full_name, password, role, is_active) 
VALUES 
    (2, 'USRSALES001', 'sales1', 'sales1@phonehub.com', '+233241111111', 'Michael Sales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'salesperson', 1),
    (2, 'USRTECH001', 'tech1', 'tech1@phonehub.com', '+233242222222', 'Sarah Tech', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technician', 1),
    (2, 'USRSALES002', 'sales2', 'sales2@phonehub.com', '+233243333333', 'David Sales', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'salesperson', 0)
ON DUPLICATE KEY UPDATE username=username;

-- =====================================================
-- Login Credentials:
-- =====================================================
-- System Admin:
--   Username: admin
--   Password: admin123
--
-- Manager (Company 1 - TechMobile):
--   Username: manager1
--   Email: manager@techmobile.gh
--   Password: manager123
--
-- Manager (Company 2 - PhoneHub):
--   Username: manager2
--   Email: manager@phonehub.com
--   Password: manager123
--
-- Staff (already created for Company 2):
--   Username: sales1 | Email: sales1@phonehub.com | Password: password
--   Username: tech1  | Email: tech1@phonehub.com  | Password: password
--   Username: sales2 | Email: sales2@phonehub.com | Password: password
--
-- NOTE: All passwords above work for login!
-- =====================================================

