-- Create company_sms_accounts table for tracking SMS credits per company
CREATE TABLE IF NOT EXISTS company_sms_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    total_sms INT NOT NULL DEFAULT 0,
    sms_used INT NOT NULL DEFAULT 0,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    sms_sender_name VARCHAR(15) DEFAULT NULL,
    custom_sms_enabled BOOLEAN NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_sms (company_id),
    INDEX idx_company_id (company_id),
    INDEX idx_status (status)
);

