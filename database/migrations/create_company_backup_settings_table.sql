-- Create company backup settings table
-- Allows system admin to configure auto-backup settings per company

CREATE TABLE IF NOT EXISTS company_backup_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    auto_backup_enabled TINYINT(1) DEFAULT 0 COMMENT 'Whether auto-backup is enabled for this company',
    backup_time TIME DEFAULT '02:00:00' COMMENT 'Time of day to run backup (24-hour format)',
    backup_destination ENUM('email', 'cloudinary', 'both') DEFAULT 'email' COMMENT 'Where to send backups: email, cloudinary, or both',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_company (company_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_auto_backup_enabled (auto_backup_enabled),
    INDEX idx_backup_time (backup_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Auto-backup configuration settings for each company';

