-- Add cloudinary_url column to backups table
-- This column stores the Cloudinary URL for backups that have been uploaded

ALTER TABLE backups 
ADD COLUMN IF NOT EXISTS cloudinary_url TEXT NULL 
COMMENT 'Cloudinary URL where backup is stored' 
AFTER file_path;

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS idx_cloudinary_url ON backups(cloudinary_url(255));

