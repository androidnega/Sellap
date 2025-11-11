-- Create sms_logs table for tracking SMS messages sent per company
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    message_type ENUM('purchase', 'swap', 'repair', 'system', 'custom', 'test_sms') NOT NULL,
    recipient VARCHAR(15) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
    sender_id VARCHAR(15) DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company_id (company_id),
    INDEX idx_message_type (message_type),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at),
    INDEX idx_recipient (recipient)
);

-- Update notification_logs table to add company_id column if it doesn't exist
-- (For backward compatibility with existing notification_logs)
ALTER TABLE notification_logs 
ADD COLUMN IF NOT EXISTS company_id INT NULL AFTER phone_number,
ADD COLUMN IF NOT EXISTS sender_id VARCHAR(15) NULL AFTER company_id;

-- Add foreign key only if column was just added
-- Note: This will fail if company_id already exists, so we handle it gracefully
-- You may need to add foreign key manually after migration

