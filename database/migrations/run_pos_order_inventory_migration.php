<?php
/**
 * Idempotent migration: pos_sales status/cancel fields, pos_sale_items.returned_quantity,
 * company_features, returns, return_items, stock_movements.
 */
require_once __DIR__ . '/../../config/database.php';

echo "Running pos_order_inventory_roles migration\n";
echo str_repeat('=', 60) . "\n";

$db = \Database::getInstance()->getConnection();

function columnExists(\PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function tableExists(\PDO $db, string $table): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(\PDO $db, string $table, string $index): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function fkExists(\PDO $db, string $fk): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?'
    );
    $stmt->execute([$fk]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    if (tableExists($db, 'pos_sales')) {
        if (!columnExists($db, 'pos_sales', 'status')) {
            $db->exec("ALTER TABLE pos_sales ADD COLUMN status ENUM('completed','cancelled','returned') NOT NULL DEFAULT 'completed'");
            echo "Added pos_sales.status\n";
        }
        if (!columnExists($db, 'pos_sales', 'cancelled_by')) {
            $db->exec('ALTER TABLE pos_sales ADD COLUMN cancelled_by BIGINT UNSIGNED NULL');
            echo "Added pos_sales.cancelled_by\n";
        }
        if (!columnExists($db, 'pos_sales', 'cancelled_role')) {
            $db->exec("ALTER TABLE pos_sales ADD COLUMN cancelled_role ENUM('sales','manager') NULL");
            echo "Added pos_sales.cancelled_role\n";
        }
        if (!columnExists($db, 'pos_sales', 'cancelled_at')) {
            $db->exec('ALTER TABLE pos_sales ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL');
            echo "Added pos_sales.cancelled_at\n";
        }
        if (!indexExists($db, 'pos_sales', 'idx_pos_sales_status')) {
            $db->exec('ALTER TABLE pos_sales ADD INDEX idx_pos_sales_status (company_id, status)');
            echo "Added index idx_pos_sales_status\n";
        }
        if (!fkExists($db, 'fk_pos_sales_cancelled_by')) {
            try {
                $db->exec('ALTER TABLE pos_sales ADD CONSTRAINT fk_pos_sales_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL');
                echo "Added FK fk_pos_sales_cancelled_by\n";
            } catch (\PDOException $e) {
                echo "Skip FK fk_pos_sales_cancelled_by: " . $e->getMessage() . "\n";
            }
        }
    }

    if (tableExists($db, 'pos_sale_items')) {
        if (!columnExists($db, 'pos_sale_items', 'returned_quantity')) {
            $db->exec('ALTER TABLE pos_sale_items ADD COLUMN returned_quantity INT UNSIGNED NOT NULL DEFAULT 0');
            echo "Added pos_sale_items.returned_quantity\n";
        }
        if (!indexExists($db, 'pos_sale_items', 'idx_pos_sale_items_returned')) {
            $db->exec('ALTER TABLE pos_sale_items ADD INDEX idx_pos_sale_items_returned (pos_sale_id, returned_quantity)');
            echo "Added index idx_pos_sale_items_returned\n";
        }
    }

    $db->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
    echo "Ensured company_features table\n";

    $db->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
    echo "Ensured returns table\n";

    $db->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
    echo "Ensured return_items table\n";

    $db->exec(<<<'SQL'
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );
    echo "Ensured stock_movements table\n";

    // Seed returns_enabled = 0 for each company (if missing)
    $companies = $db->query('SELECT id FROM companies')->fetchAll(PDO::FETCH_COLUMN);
    $ins = $db->prepare(
        'INSERT IGNORE INTO company_features (company_id, feature_key, enabled) VALUES (?, ?, 0)'
    );
    foreach ($companies as $cid) {
        $ins->execute([(int)$cid, 'returns_enabled']);
    }
    echo "Seeded company_features.returns_enabled (disabled by default)\n";

    echo "\nDone.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
