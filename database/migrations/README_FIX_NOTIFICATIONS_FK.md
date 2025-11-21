# Fix Notifications Foreign Key Constraint Error

## Problem
When uploading your database to cPanel phpMyAdmin, you may encounter this error:

```
#1452 - Cannot add or update a child row: a foreign key constraint fails 
(`manuelc8_sellapp`.`#sql-alter-31ff4d-866bba`, CONSTRAINT `notifications_ibfk_1` 
FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE)
```

This error occurs because there are orphaned records in the `notifications` table that reference `user_id` values that don't exist in the `users` table.

## Solution

You have three options to fix this:

### Option 1: Quick SQL Fix (Recommended for phpMyAdmin)

1. Open phpMyAdmin in cPanel
2. Select your database
3. Go to the SQL tab
4. Copy and paste the contents of `fix_notifications_foreign_keys_simple.sql`
5. Click "Go" to execute

This will:
- Delete orphaned notifications (notifications with invalid user_id or company_id)
- Add the foreign key constraints

### Option 2: Detailed SQL Fix

1. Open phpMyAdmin in cPanel
2. Select your database
3. Go to the SQL tab
4. Copy and paste the contents of `fix_notifications_foreign_keys.sql`
5. Click "Go" to execute

This provides more detailed output and verification steps.

### Option 3: PHP Script Fix (For Local Development)

If you're working locally and have access to the command line:

```bash
cd database/migrations
php fix_notifications_foreign_keys.php
```

This script will:
- Check for orphaned notifications
- Delete orphaned records
- Drop existing constraints (if any)
- Add the foreign key constraints
- Verify the fix

## What Causes This?

This issue typically occurs when:
1. Users were deleted but their notifications were not cleaned up
2. Data was imported/exported in the wrong order
3. Manual database modifications left orphaned records

## Prevention

The `schema.sql` file has been updated to include the notifications table definition with proper foreign key constraints. For fresh installations, use `schema.sql` which will create the table correctly from the start.

## Verification

After running the fix, you can verify that all notifications have valid foreign keys by running:

```sql
SELECT COUNT(*) as orphaned_count
FROM notifications n
LEFT JOIN users u ON n.user_id = u.id
LEFT JOIN companies c ON n.company_id = c.id
WHERE u.id IS NULL OR c.id IS NULL;
```

This should return `0` (no orphaned records).

## Files

- `fix_notifications_foreign_keys_simple.sql` - Quick fix for phpMyAdmin
- `fix_notifications_foreign_keys.sql` - Detailed fix with verification
- `fix_notifications_foreign_keys.php` - PHP script for command line use

