-- Alerts and Alert Notifications Tables
-- Phase 3: Advanced Intelligence & Audit Logging

CREATE TABLE IF NOT EXISTS alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT NULL,
  key_name VARCHAR(100) NOT NULL COMMENT 'e.g., low_stock, low_sms, profit_drop',
  title VARCHAR(255) NOT NULL,
  condition_json JSON NOT NULL COMMENT 'serialized rule (threshold, window, type)',
  severity ENUM('info', 'warning', 'critical') DEFAULT 'warning',
  enabled TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_company_id (company_id),
  INDEX idx_key_name (key_name),
  INDEX idx_enabled (enabled),
  UNIQUE KEY uk_company_key (company_id, key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Alert rules and configurations';

CREATE TABLE IF NOT EXISTS alert_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alert_id INT NOT NULL,
  company_id BIGINT NULL,
  triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  payload JSON NULL COMMENT 'alert context data',
  channels JSON NULL COMMENT 'e.g., ["dashboard","email","sms"]',
  handled TINYINT(1) DEFAULT 0,
  handled_by INT NULL COMMENT 'user_id who acknowledged',
  handled_at TIMESTAMP NULL,
  
  INDEX idx_alert_id (alert_id),
  INDEX idx_company_id (company_id),
  INDEX idx_handled (handled),
  INDEX idx_triggered_at (triggered_at),
  INDEX idx_company_unhandled (company_id, handled, triggered_at),
  
  FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Triggered alert notifications';

