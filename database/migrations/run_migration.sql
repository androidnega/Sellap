-- Quick migration runner for reset system tables
-- Run this in phpMyAdmin or MySQL command line

USE sellapp_db;

-- =====================================================
-- ADMIN_ACTIONS TABLE (Audit log for destructive operations)
-- =====================================================
CREATE TABLE IF NOT EXISTS admin_actions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT UNSIGNED NOT NULL,
  action_type ENUM('company_reset','system_reset') NOT NULL,
  target_company_id BIGINT UNSIGNED NULL,
  dry_run TINYINT(1) DEFAULT 0 NOT NULL,
  payload JSON NULL COMMENT 'Original request payload (confirmation text, etc.)',
  row_counts JSON NULL COMMENT 'Affected row counts per table after run',
  backup_reference VARCHAR(255) NULL COMMENT 'Backup file path or reference ID',
  status ENUM('pending','completed','failed','cancelled') DEFAULT 'pending',
  error_message TEXT NULL,
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_user (admin_user_id),
  INDEX idx_action_type (action_type),
  INDEX idx_target_company (target_company_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (target_company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- RESET_JOBS TABLE (Track async file cleanup and notifications)
-- =====================================================
CREATE TABLE IF NOT EXISTS reset_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_action_id INT NOT NULL,
  job_type ENUM('file_cleanup','email_notify','database_cleanup') NOT NULL,
  status ENUM('pending','running','completed','failed') DEFAULT 'pending',
  details JSON NULL COMMENT 'Job-specific details (file paths, counts, etc.)',
  error_message TEXT NULL,
  retry_count INT DEFAULT 0,
  max_retries INT DEFAULT 3,
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_admin_action (admin_action_id),
  INDEX idx_job_type (job_type),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  FOREIGN KEY (admin_action_id) REFERENCES admin_actions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

