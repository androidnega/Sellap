-- Create notification_logs table for tracking SMS notifications
CREATE TABLE IF NOT EXISTS notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_phone (phone_number),
    INDEX idx_created_at (created_at)
);

