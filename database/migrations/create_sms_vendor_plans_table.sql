-- Create sms_vendor_plans table for storing vendor SMS bundles
CREATE TABLE IF NOT EXISTS sms_vendor_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_name VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    cost_amount DECIMAL(12,2) NOT NULL,
    messages INT NOT NULL,
    expires_in_days INT NULL COMMENT 'NULL = No expiry',
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vendor_name (vendor_name),
    INDEX idx_cost_amount (cost_amount),
    INDEX idx_messages (messages)
);

