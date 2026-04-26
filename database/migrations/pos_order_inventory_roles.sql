-- POS orders (pos_sales / pos_sale_items) inventory: cancellations, returns, stock audit.
-- Run via database/migrations/run_pos_order_inventory_migration.php

-- Order lifecycle (pos_sales = orders)
ALTER TABLE pos_sales
    ADD COLUMN status ENUM('completed','cancelled','returned') NOT NULL DEFAULT 'completed' AFTER payment_status,
    ADD COLUMN cancelled_by BIGINT UNSIGNED NULL AFTER status,
    ADD COLUMN cancelled_role ENUM('sales','manager') NULL AFTER cancelled_by,
    ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER cancelled_role,
    ADD INDEX idx_pos_sales_status (company_id, status);

ALTER TABLE pos_sales
    ADD CONSTRAINT fk_pos_sales_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL;

-- Order line returns tracking (pos_sale_items = order_items)
ALTER TABLE pos_sale_items
    ADD COLUMN returned_quantity INT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity,
    ADD INDEX idx_pos_sale_items_returned (pos_sale_id, returned_quantity);

-- Company-level feature flags (admin)
CREATE TABLE IF NOT EXISTS company_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    feature_key VARCHAR(64) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_company_feature (company_id, feature_key),
    INDEX idx_company_features_company (company_id),
    CONSTRAINT fk_company_features_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_features_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Returns header (manager)
CREATE TABLE IF NOT EXISTS `returns` (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    pos_sale_id BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_role VARCHAR(32) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_returns_company (company_id),
    INDEX idx_returns_sale (pos_sale_id),
    CONSTRAINT fk_returns_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_returns_pos_sale FOREIGN KEY (pos_sale_id) REFERENCES pos_sales(id) ON DELETE RESTRICT,
    CONSTRAINT fk_returns_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS return_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_id BIGINT UNSIGNED NOT NULL,
    pos_sale_item_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    quantity INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_return_items_return (return_id),
    INDEX idx_return_items_sale_line (pos_sale_item_id),
    CONSTRAINT fk_return_items_return FOREIGN KEY (return_id) REFERENCES `returns`(id) ON DELETE CASCADE,
    CONSTRAINT fk_return_items_pos_line FOREIGN KEY (pos_sale_item_id) REFERENCES pos_sale_items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock audit trail (all adjustments go through here)
CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    type ENUM('sale','cancel','return') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    reference_id BIGINT UNSIGNED NULL,
    reference_type VARCHAR(50) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_role VARCHAR(32) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sm_company_time (company_id, created_at),
    INDEX idx_sm_product (product_id),
    INDEX idx_sm_type (type),
    CONSTRAINT fk_stock_movements_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_stock_movements_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
