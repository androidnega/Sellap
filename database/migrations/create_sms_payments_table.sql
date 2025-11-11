-- Create sms_payments table for tracking SMS credit purchases
CREATE TABLE IF NOT EXISTS sms_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    payment_id VARCHAR(255) NOT NULL UNIQUE,
    payment_provider ENUM('paypal', 'stripe', 'manual') NOT NULL DEFAULT 'paypal',
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'GHS',
    sms_credits INT NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    paypal_order_id VARCHAR(255) NULL,
    paypal_payer_id VARCHAR(255) NULL,
    payment_details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company_id (company_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_paypal_order_id (paypal_order_id)
);

