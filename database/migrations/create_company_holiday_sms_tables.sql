-- Holiday / scheduled customer SMS (per company). Run via run_company_holiday_sms_migration.php

CREATE TABLE IF NOT EXISTS company_holiday_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(160) NOT NULL,
    event_date DATE NOT NULL COMMENT 'For one-time sends; for annual, year is ignored and month/day are used',
    is_annual TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = repeat every year on month/day',
    message_body TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chm_company (company_id),
    INDEX idx_chm_active (company_id, is_active),
    CONSTRAINT fk_chm_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_chm_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holiday_sms_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    holiday_id BIGINT UNSIGNED NOT NULL,
    run_date DATE NOT NULL COMMENT 'Local server calendar date when auto-send ran',
    customers_sent INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    error_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_holiday_run_day (holiday_id, run_date),
    INDEX idx_hsr_company (company_id),
    CONSTRAINT fk_hsr_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_hsr_holiday FOREIGN KEY (holiday_id) REFERENCES company_holiday_messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
