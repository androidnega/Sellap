-- =====================================================
-- Fix Notifications Foreign Key Constraints
-- This script cleans up orphaned notifications before adding foreign key constraints
-- Run this BEFORE adding foreign key constraints to the notifications table
-- =====================================================

-- Step 1: Check for orphaned notifications (notifications with user_id that doesn't exist in users table)
-- This query will show you how many orphaned records exist
SELECT 
    COUNT(*) as orphaned_notifications_count
FROM notifications n
LEFT JOIN users u ON n.user_id = u.id
WHERE u.id IS NULL;

-- Step 2: Check for notifications with invalid company_id
SELECT 
    COUNT(*) as orphaned_company_notifications_count
FROM notifications n
LEFT JOIN companies c ON n.company_id = c.id
WHERE c.id IS NULL;

-- Step 3: Delete orphaned notifications (notifications with user_id that doesn't exist)
-- This removes notifications that reference non-existent users
DELETE n FROM notifications n
LEFT JOIN users u ON n.user_id = u.id
WHERE u.id IS NULL;

-- Step 4: Delete notifications with invalid company_id
-- This removes notifications that reference non-existent companies
DELETE n FROM notifications n
LEFT JOIN companies c ON n.company_id = c.id
WHERE c.id IS NULL;

-- Step 5: Now you can safely add the foreign key constraints
-- If the constraints already exist and are causing errors, drop them first:
-- ALTER TABLE notifications DROP FOREIGN KEY IF EXISTS notifications_ibfk_1;
-- ALTER TABLE notifications DROP FOREIGN KEY IF EXISTS notifications_ibfk_2;

-- Step 6: Add the foreign key constraints
-- Note: Only run this if the constraints don't already exist
ALTER TABLE notifications
  ADD CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  ADD CONSTRAINT notifications_ibfk_2 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE;

-- =====================================================
-- Verification: Check that all notifications now have valid foreign keys
-- =====================================================
-- This should return 0 (no orphaned records)
SELECT 
    COUNT(*) as remaining_orphaned_notifications
FROM notifications n
LEFT JOIN users u ON n.user_id = u.id
LEFT JOIN companies c ON n.company_id = c.id
WHERE u.id IS NULL OR c.id IS NULL;

