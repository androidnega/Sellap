-- Backups Metadata Table
-- Phase 5: Data Resilience & Cross-System Intelligence

CREATE TABLE IF NOT EXISTS backups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path TEXT NOT NULL,
  file_size BIGINT NULL COMMENT 'File size in bytes',
  status ENUM('completed', 'failed', 'in_progress') DEFAULT 'completed',
  record_count INT NULL COMMENT 'Number of records backed up',
  format VARCHAR(10) NULL COMMENT 'json, sql, zip',
  created_by BIGINT NULL COMMENT 'user_id who created the backup',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_company_id (company_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  INDEX idx_company_created (company_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Metadata for company data backups';

