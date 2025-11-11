-- Scheduled Reports Table
-- Phase 3: Advanced Intelligence & Audit Logging

CREATE TABLE IF NOT EXISTS scheduled_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT NULL,
  name VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL COMMENT 'e.g., daily_sales, weekly_inventory, monthly_profit',
  cron_expr VARCHAR(64) NOT NULL COMMENT 'Cron expression for scheduling',
  last_run TIMESTAMP NULL,
  next_run TIMESTAMP NULL,
  parameters JSON NULL COMMENT 'report-specific parameters (format, recipients, etc.)',
  enabled TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_company_id (company_id),
  INDEX idx_enabled (enabled),
  INDEX idx_next_run (next_run),
  INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Scheduled report configurations';

