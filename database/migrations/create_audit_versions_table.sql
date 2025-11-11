-- Audit Versions Table - Data Versioning & Audit Lock
-- Phase 5: Data Resilience & Cross-System Intelligence

CREATE TABLE IF NOT EXISTS audit_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT NULL,
  table_name VARCHAR(100) NOT NULL COMMENT 'Name of the table being versioned',
  record_id BIGINT NOT NULL COMMENT 'ID of the record being versioned',
  action ENUM('create', 'update', 'delete') NOT NULL,
  old_data JSON NULL COMMENT 'Previous state of the record',
  new_data JSON NULL COMMENT 'New state of the record',
  user_id BIGINT NULL COMMENT 'User who made the change',
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_company_id (company_id),
  INDEX idx_table_record (table_name, record_id),
  INDEX idx_created_at (created_at),
  INDEX idx_user_id (user_id),
  INDEX idx_company_table (company_id, table_name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Version history for data changes - enables rollback and audit trail';

