-- Inventory travel / audit sessions: START/END snapshots for manager trips
CREATE TABLE IF NOT EXISTS inventory_travel_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    manager_user_id BIGINT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    staff_mode ENUM('single', 'all') NULL DEFAULT NULL,
    staff_user_id BIGINT UNSIGNED NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_status (company_id, status),
    INDEX idx_company_started (company_id, started_at),
    CONSTRAINT fk_its_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_its_manager FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_its_staff FOREIGN KEY (staff_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_travel_snapshot_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    phase ENUM('START', 'END') NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_phase_product (session_id, phase, product_id),
    INDEX idx_session_phase (session_id, phase),
    CONSTRAINT fk_itsl_session FOREIGN KEY (session_id) REFERENCES inventory_travel_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
