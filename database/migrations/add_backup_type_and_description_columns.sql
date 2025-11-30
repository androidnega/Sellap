-- Migration: Add backup_type and description columns to backups table
-- Date: 2025-11-24
-- Purpose: Support automatic/manual backup classification and descriptions

-- Add backup_type column
ALTER TABLE backups 
ADD COLUMN IF NOT EXISTS backup_type VARCHAR(20) DEFAULT 'manual' 
COMMENT 'Type of backup: manual or automatic'
AFTER format;

-- Add description column
ALTER TABLE backups 
ADD COLUMN IF NOT EXISTS description TEXT NULL 
COMMENT 'Optional description or notes about the backup'
AFTER backup_type;

-- Add index for backup_type for faster filtering
ALTER TABLE backups 
ADD INDEX IF NOT EXISTS idx_backup_type (backup_type);

-- Update existing backups that have '[AUTOMATIC DAILY BACKUP]' marker (if any exist from legacy code)
-- This handles the case where backups were created before this migration
UPDATE backups 
SET backup_type = 'automatic' 
WHERE description LIKE '%[AUTOMATIC DAILY BACKUP]%'
AND backup_type = 'manual';


