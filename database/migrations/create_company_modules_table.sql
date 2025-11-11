-- =====================================================
-- Company Modules Table Migration
-- SellApp - Module Toggle System (Phase 1)
-- =====================================================
-- This table stores which modules are enabled for each company
-- Allows per-company module configuration for multi-tenant system

CREATE TABLE IF NOT EXISTS company_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    module_key VARCHAR(100) NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_module (company_id, module_key),
    INDEX idx_company_id (company_id),
    INDEX idx_module_key (module_key),
    INDEX idx_enabled (enabled),
    INDEX idx_company_enabled (company_id, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Example Module Keys (based on SYSTEM_MODULE_AUDIT.json)
-- =====================================================
-- Recommended module keys:
-- - products_inventory
-- - pos_sales
-- - swap
-- - repairs
-- - customers
-- - staff_management
-- - reports_analytics
-- - notifications_sms

