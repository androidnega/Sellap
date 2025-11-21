-- =====================================================
-- SellApp Database Schema
-- Database: sellapp_db
-- Version: 2.0.0 - Phase 7 (Multi-Tenant Architecture)
-- =====================================================

-- Drop existing tables if needed (for fresh install)
-- DROP TABLE IF EXISTS customers;
-- DROP TABLE IF EXISTS users;
-- DROP TABLE IF EXISTS companies;

-- =====================================================
-- COMPANIES TABLE (Multi-Tenant Support)
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
-- USERS TABLE (Updated for Multi-Tenant)
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NULL,
    unique_id VARCHAR(50) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    full_name VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('system_admin', 'manager', 'salesperson', 'technician') DEFAULT 'salesperson',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_unique_id (unique_id),
    INDEX idx_role (role),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CUSTOMERS TABLE (Multi-Tenant Support)
-- =====================================================
CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    unique_id VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_phone (phone_number),
    INDEX idx_unique_id (unique_id),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SEED DATA - Default System Administrator
-- =====================================================
-- Password: admin123 (bcrypt hashed)
-- System Admin has no company_id (manages all companies)
INSERT INTO users (company_id, unique_id, username, email, phone_number, full_name, password, role, is_active) 
VALUES 
    (NULL, 'USRADMIN001', 'admin', 'admin@sellapp.com', '+233000000000', 'System Administrator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'system_admin', 1)
ON DUPLICATE KEY UPDATE username=username;

-- =====================================================
-- NOTIFICATIONS TABLE (User Notifications - Multi-Tenant)
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    type ENUM('repair','stock','swap','sale','system') DEFAULT 'system',
    status ENUM('unread','read') DEFAULT 'unread',
    data LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(data)),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_company (company_id),
    KEY idx_status (status),
    KEY idx_type (type),
    KEY idx_created (created_at),
    CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT notifications_ibfk_2 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PHONES TABLE (Inventory & Swapping - Multi-Tenant)
-- =====================================================
CREATE TABLE IF NOT EXISTS phones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    unique_id VARCHAR(50) UNIQUE NOT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    imei VARCHAR(20) UNIQUE,
    phone_condition ENUM('new', 'excellent', 'good', 'fair', 'poor') DEFAULT 'good',
    purchase_price DECIMAL(10, 2),
    selling_price DECIMAL(10, 2),
    phone_value DECIMAL(10, 2) NOT NULL,
    status ENUM('AVAILABLE', 'SOLD', 'RESERVED', 'SWAPPED') DEFAULT 'AVAILABLE',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_unique_id (unique_id),
    INDEX idx_imei (imei),
    INDEX idx_status (status),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SWAPS TABLE (Phone Exchange Records - Multi-Tenant)
-- =====================================================
CREATE TABLE IF NOT EXISTS swaps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    unique_id VARCHAR(50) UNIQUE NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    new_phone_id BIGINT UNSIGNED NOT NULL,
    given_phone_description TEXT,
    given_phone_value DECIMAL(10, 2) DEFAULT 0,
    final_price DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('CASH', 'MOBILE_MONEY', 'CARD', 'BANK_TRANSFER') DEFAULT 'CASH',
    swap_status ENUM('PENDING', 'COMPLETED', 'CANCELLED') DEFAULT 'COMPLETED',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (new_phone_id) REFERENCES phones(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_unique_id (unique_id),
    INDEX idx_customer (customer_id),
    INDEX idx_status (swap_status),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REPAIRS TABLE (Repair Tracking - Multi-Tenant)
-- =====================================================
CREATE TABLE IF NOT EXISTS repairs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    unique_id VARCHAR(50) UNIQUE NOT NULL,
    tracking_code VARCHAR(20) UNIQUE NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    phone_description VARCHAR(255) NOT NULL,
    imei VARCHAR(20),
    issue_description TEXT NOT NULL,
    repair_cost DECIMAL(10, 2) DEFAULT 0,
    parts_cost DECIMAL(10, 2) DEFAULT 0,
    total_cost DECIMAL(10, 2) DEFAULT 0,
    repair_status ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'DELIVERED', 'CANCELLED') DEFAULT 'PENDING',
    payment_status ENUM('UNPAID', 'PARTIAL', 'PAID') DEFAULT 'UNPAID',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_unique_id (unique_id),
    INDEX idx_tracking (tracking_code),
    INDEX idx_customer (customer_id),
    INDEX idx_status (repair_status),
    INDEX idx_payment (payment_status),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- POS SALES TABLE (Point of Sale Transactions - Multi-Tenant)
-- =====================================================
CREATE TABLE IF NOT EXISTS pos_sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    unique_id VARCHAR(50) UNIQUE NOT NULL,
    customer_id BIGINT UNSIGNED,
    total_amount DECIMAL(10, 2) NOT NULL,
    discount DECIMAL(10, 2) DEFAULT 0,
    tax DECIMAL(10, 2) DEFAULT 0,
    final_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('CASH', 'MOBILE_MONEY', 'CARD', 'BANK_TRANSFER') DEFAULT 'CASH',
    payment_status ENUM('PAID', 'PARTIAL', 'UNPAID') DEFAULT 'PAID',
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_unique_id (unique_id),
    INDEX idx_customer (customer_id),
    INDEX idx_date (created_at),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- POS SALE ITEMS TABLE (Individual Items in Sales)
-- =====================================================
CREATE TABLE IF NOT EXISTS pos_sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pos_sale_id BIGINT UNSIGNED NOT NULL,
    item_type ENUM('PHONE', 'ACCESSORY', 'PART', 'SERVICE', 'OTHER') DEFAULT 'OTHER',
    item_id BIGINT UNSIGNED,
    item_description VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pos_sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE,
    INDEX idx_sale (pos_sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

