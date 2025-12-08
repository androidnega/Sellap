-- =====================================================
-- USER ACTIVITY LOGS TABLE
-- Tracks user login/logout and session duration
-- =====================================================
CREATE TABLE IF NOT EXISTS user_activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED,
    user_role VARCHAR(50) NOT NULL,
    username VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    event_type ENUM('login', 'logout', 'session_timeout') NOT NULL,
    login_time TIMESTAMP NULL,
    logout_time TIMESTAMP NULL,
    session_duration_seconds INT DEFAULT 0,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_company (company_id),
    INDEX idx_role (user_role),
    INDEX idx_event_type (event_type),
    INDEX idx_login_time (login_time),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

