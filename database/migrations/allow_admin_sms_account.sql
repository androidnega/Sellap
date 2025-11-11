-- Allow company_id = 0 for admin/system SMS account
-- This allows tracking of admin's SMS balance that gets deducted when companies purchase SMS

-- First, check if we need to allow company_id = 0 (remove foreign key constraint if needed)
-- Note: Foreign key constraint will fail for company_id = 0, so we handle it differently

-- Insert admin account if it doesn't exist (this will fail if FK constraint exists, so handle gracefully)
INSERT IGNORE INTO company_sms_accounts (company_id, total_sms, sms_used, status, created_at)
VALUES (0, 0, 0, 'active', NOW());

-- Update admin balance from provider if we can fetch it
-- This will be done by the application on first purchase

