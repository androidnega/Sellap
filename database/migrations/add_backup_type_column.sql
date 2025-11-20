-- Add backup_type column to backups table for automatic/manual tracking

ALTER TABLE backups 
ADD COLUMN IF NOT EXISTS backup_type ENUM('manual', 'automatic') DEFAULT 'manual' AFTER status;

-- Add index for backup_type
ALTER TABLE backups 
ADD INDEX IF NOT EXISTS idx_backup_type (backup_type);

