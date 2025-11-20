-- Restore Points Table
-- Allows companies to create restore points and restore to specific dates/states

CREATE TABLE IF NOT EXISTS restore_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id BIGINT NOT NULL,
  name VARCHAR(255) NOT NULL COMMENT 'User-friendly name for the restore point',
  description TEXT NULL COMMENT 'Optional description of what this restore point contains',
  backup_id BIGINT NULL COMMENT 'Reference to the backup file used for this restore point',
  backup_file_path TEXT NULL COMMENT 'Path to the backup file',
  snapshot_data JSON NULL COMMENT 'Snapshot of key metrics at time of creation',
  total_records INT NULL COMMENT 'Total number of records in the restore point',
  tables_included TEXT NULL COMMENT 'Comma-separated list of tables included',
  created_by BIGINT NULL COMMENT 'user_id who created the restore point',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  restored_at TIMESTAMP NULL COMMENT 'When this restore point was last used for restoration',
  restore_count INT DEFAULT 0 COMMENT 'Number of times this restore point has been used',
  
  INDEX idx_company_id (company_id),
  INDEX idx_created_at (created_at),
  INDEX idx_company_created (company_id, created_at),
  INDEX idx_backup_id (backup_id)
  -- Foreign keys removed to avoid constraint issues - can be added later if needed
  -- FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  -- FOREIGN KEY (backup_id) REFERENCES backups(id) ON DELETE SET NULL,
  -- FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Restore points for companies - allows restoring to specific data states';

