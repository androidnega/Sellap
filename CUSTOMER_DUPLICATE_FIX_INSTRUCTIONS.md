# Customer Duplicate Fix - Quick Start Guide

## Problem You're Experiencing
You created two customers and one is showing as duplicate, with the first one hidden and cannot be seen on your live server.

Customer details:
- **ID:** CUS69230F6177C5F
- **Name:** wer
- **Phone:** 43453456789
- **Date:** Nov 23, 2025

## What Happened
This occurred because duplicate customers were created with the same phone number. This can happen due to:
- Double-clicking the submit button
- Slow network causing duplicate submissions
- Clicking back and resubmitting the form

## The Fix (4 Easy Steps)

### Step 1: Check for Duplicates ✓
First, let's see what duplicates exist:

```bash
cd C:\xampp\htdocs\sellapp
php database/check_duplicates.php
```

This will show you all duplicate customers without making any changes.

**Expected output:**
- It will show the duplicate customer with phone `43453456789`
- It will tell you how many duplicates will be removed

### Step 2: Backup Your Database ✓
**VERY IMPORTANT:** Always backup first!

```bash
# Open Command Prompt or PowerShell
cd C:\xampp\mysql\bin
.\mysqldump.exe -u root -p sellapp_db > C:\backup_sellapp_%date%.sql
```

Enter your MySQL password when prompted.

### Step 3: Run the Cleanup ✓
This will remove duplicate customers and merge their data:

```bash
cd C:\xampp\htdocs\sellapp
php database/cleanup_duplicate_customers.php
```

**What it does:**
- Finds all duplicate customers by phone number
- Keeps the oldest customer (first created)
- Moves all related data (sales, repairs, swaps) to the kept customer
- Deletes the duplicate records

**Your specific case:**
- It will keep one customer with phone `43453456789`
- It will delete the duplicate
- After this, you'll see only ONE customer in your list

### Step 4: Prevent Future Duplicates ✓
Add a database constraint to prevent this from happening again:

```bash
cd C:\xampp\mysql\bin
.\mysql.exe -u root -p sellapp_db < C:\xampp\htdocs\sellapp\database\add_unique_constraint_customers.sql
```

This ensures that the database will reject any duplicate phone numbers within the same company.

## Verify It's Fixed

### Test 1: Check Customer List
1. Open your browser
2. Go to your SellApp: `http://localhost/sellapp/dashboard/customers`
3. You should now see **only ONE** customer with phone `43453456789`
4. The duplicate badge should be gone

### Test 2: Try Creating a Duplicate
1. Click "Add New Customer"
2. Enter:
   - Name: `Test Customer`
   - Phone: `43453456789` (same as existing customer)
   - Email: `test@test.com`
3. Click "Create Customer"
4. You should see an error: **"A customer with this phone number already exists"**
5. This proves the fix is working! ✓

## Advanced Testing (Optional)

If you want to run automated tests:

```bash
cd C:\xampp\htdocs\sellapp
php tests/test_customer_duplicate_prevention.php
```

This will automatically test:
- Creating a customer
- Finding customers by phone and email
- Rejecting duplicate phone numbers
- Cleaning up test data

## Troubleshooting

### Issue: "PHP is not recognized"
**Solution:** Add PHP to your PATH or use full path:
```bash
C:\xampp\php\php.exe database\check_duplicates.php
```

### Issue: "MySQL is not recognized"
**Solution:** Use full path:
```bash
C:\xampp\mysql\bin\mysql.exe -u root -p
```

### Issue: "Access denied for user 'root'"
**Solution:** 
1. Check your MySQL password
2. Or update `config/database.php` with correct credentials

### Issue: Script shows errors
**Solution:**
1. Check PHP error log: `C:\xampp\php\logs\php_error_log`
2. Check Apache error log: `C:\xampp\apache\logs\error.log`
3. Restore from backup if needed

## Rollback (If Something Goes Wrong)

If you need to undo the changes:

```bash
cd C:\xampp\mysql\bin
.\mysql.exe -u root -p sellapp_db < C:\backup_sellapp_[date].sql
```

Replace `[date]` with the actual backup file name.

## What Changed in Your Code

### Files Modified:
1. **app/Controllers/CustomerController.php**
   - Improved duplicate checking (lines 383-434)
   - Now uses efficient database queries instead of loading all customers

2. **app/Models/Customer.php**
   - Added new method: `findByEmailInCompany()`
   - Better duplicate detection

3. **Database Schema**
   - Added unique constraint: `UNIQUE (company_id, phone_number)`
   - Prevents duplicates at database level

### New Files Created:
1. **database/check_duplicates.php** - Check for duplicates (no changes made)
2. **database/cleanup_duplicate_customers.php** - Remove duplicates safely
3. **database/add_unique_constraint_customers.sql** - Add database constraint
4. **tests/test_customer_duplicate_prevention.php** - Automated tests
5. **database/DUPLICATE_CUSTOMERS_FIX.md** - Detailed technical documentation

## Summary

**Before Fix:**
- ✗ Duplicates could be created
- ✗ One customer showing twice
- ✗ First customer hidden

**After Fix:**
- ✓ Duplicates removed
- ✓ Only one customer visible
- ✓ Future duplicates prevented
- ✓ Database constraint in place

## Need Help?

If you encounter any issues:
1. Check the error message carefully
2. Look at the PHP error logs
3. Make sure you have a database backup
4. Review `database/DUPLICATE_CUSTOMERS_FIX.md` for detailed technical info

## Quick Reference Commands

```bash
# 1. Check duplicates (no changes)
php database/check_duplicates.php

# 2. Backup database
C:\xampp\mysql\bin\mysqldump.exe -u root -p sellapp_db > backup.sql

# 3. Clean duplicates
php database/cleanup_duplicate_customers.php

# 4. Add constraint
C:\xampp\mysql\bin\mysql.exe -u root -p sellapp_db < database/add_unique_constraint_customers.sql

# 5. Test (optional)
php tests/test_customer_duplicate_prevention.php

# 6. Restore if needed
C:\xampp\mysql\bin\mysql.exe -u root -p sellapp_db < backup.sql
```

---

**That's it!** After completing these steps, your duplicate customer issue will be resolved and prevented from happening again. 🎉

