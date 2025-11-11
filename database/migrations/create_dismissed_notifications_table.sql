-- Dismissed Notifications Table
-- Stores company-wide dismissed notifications so when a manager clears a notification,
-- it's cleared for all users in that company (salesperson, repairer, etc.)

CREATE TABLE IF NOT EXISTS dismissed_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT UNSIGNED NOT NULL,
  notification_id VARCHAR(255) NOT NULL COMMENT 'Notification identifier (e.g., low_stock_123, out_of_stock_456)',
  dismissed_by INT NOT NULL COMMENT 'User ID who dismissed the notification',
  dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_company_notification (company_id, notification_id),
  INDEX idx_company (company_id),
  INDEX idx_dismissed_at (dismissed_at),
  
  UNIQUE KEY uk_company_notification (company_id, notification_id),
  
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  FOREIGN KEY (dismissed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Company-wide dismissed notifications';

