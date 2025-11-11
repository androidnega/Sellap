-- Smart Recommendations Table
-- Phase 4: Predictive Insights & AI Advisory

CREATE TABLE IF NOT EXISTS smart_recommendations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  type ENUM('sales', 'inventory', 'swap', 'repair', 'profit', 'general') DEFAULT 'general',
  priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
  confidence FLOAT DEFAULT 0.0 COMMENT 'Confidence score 0.0-1.0',
  status ENUM('unread', 'read') DEFAULT 'unread',
  action_url VARCHAR(500) NULL COMMENT 'Optional URL to action page',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL COMMENT 'Recommendation expires after this date',
  
  INDEX idx_company_id (company_id),
  INDEX idx_status (status),
  INDEX idx_type (type),
  INDEX idx_priority (priority),
  INDEX idx_created_at (created_at),
  INDEX idx_company_unread (company_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='AI-generated smart recommendations for managers';

