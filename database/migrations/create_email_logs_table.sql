-- Create email_logs table to track all emails sent
CREATE TABLE IF NOT EXISTS email_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    email_type ENUM('automatic', 'manual', 'test', 'monthly_report', 'backup', 'notification') DEFAULT 'manual',
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    company_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    role VARCHAR(50) NULL COMMENT 'User role if sent to user',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_recipient (recipient_email),
    INDEX idx_email_type (email_type),
    INDEX idx_status (status),
    INDEX idx_company_id (company_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_sent_at (sent_at),
    
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs all emails sent by the system';

