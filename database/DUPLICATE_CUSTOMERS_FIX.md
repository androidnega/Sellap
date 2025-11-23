# Duplicate Customers Fix

## Problem
Duplicate customer records were being created with the same phone number within the same company. This happened due to:
1. Double-clicks on the submit button
2. Network latency causing duplicate requests
3. Missing database-level uniqueness constraint

## Solution
This fix implements a comprehensive solution with three components:

### 1. Database Cleanup Script
**File:** `database/cleanup_duplicate_customers.php`

Removes existing duplicate customers while preserving data integrity:
- Identifies duplicate customers by phone number within each company
- Keeps the oldest customer record
- Migrates all related data (sales, repairs, swaps) to the kept customer
- Safely deletes duplicate records

### 2. Application-Level Prevention
**Files:** 
- `app/Controllers/CustomerController.php`
- `app/Models/Customer.php`

Enhanced duplicate checking before insertion:
- Efficient database queries to check for existing phone numbers
- Added `findByEmailInCompany()` method for email uniqueness
- Better error messages with existing customer details

### 3. Database Constraint
**File:** `database/add_unique_constraint_customers.sql`

Adds a unique constraint at database level:
- Ensures `(company_id, phone_number)` combination is unique
- Prevents duplicates even if application logic fails
- Database-level enforcement is the most reliable

## Installation Steps

### Step 1: Backup Your Database
**IMPORTANT: Always backup before running cleanup scripts!**

```bash
# On your live server, backup the database
mysqldump -u root -p sellapp_db > backup_before_cleanup_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Run the Cleanup Script
Navigate to your project root and run:

```bash
# From project root directory
php database/cleanup_duplicate_customers.php
```

**What it does:**
- Scans for duplicate customers
- Shows you what will be deleted
- Merges related data to the oldest customer
- Provides detailed output of all changes

**Example Output:**
```
=== Customer Duplicate Cleanup Script ===
Starting at: 2025-11-23 10:30:00

Found 1 groups of duplicate customers:

─────────────────────────────────────────
Company ID: 1
Phone: 43453456789
Duplicates found: 2
Customer IDs: 123, 124
Keeping: ID 123 (oldest)
  → CUS69230F6177C5F - wer (created: 2025-11-23 09:00:00)
Deleting: 124
  ✗ CUS69230F6177C5F - wer (created: 2025-11-23 09:00:05)

=== Cleanup Complete ===
Total duplicate customers deleted: 1
Finished at: 2025-11-23 10:30:01
```

### Step 3: Add Database Constraint
After cleanup is successful, add the unique constraint:

```bash
# Replace 'root' with your MySQL username if different
mysql -u root -p sellapp_db < database/add_unique_constraint_customers.sql
```

This ensures no future duplicates can be created at the database level.

### Step 4: Test the Fix
1. Try creating a customer with a phone number
2. Try creating another customer with the same phone number
3. You should see an error: "A customer with this phone number already exists"
4. Verify the customer list shows no duplicates

## Verification

### Check for Remaining Duplicates
```sql
SELECT 
    company_id,
    phone_number,
    COUNT(*) as count
FROM customers
WHERE phone_number IS NOT NULL AND phone_number != ''
GROUP BY company_id, phone_number
HAVING COUNT(*) > 1;
```

Should return **0 rows** after cleanup.

### Check if Constraint Exists
```sql
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'sellapp_db'
AND TABLE_NAME = 'customers'
AND CONSTRAINT_NAME = 'unique_company_phone';
```

Should return **1 row** showing the constraint.

## Rollback (If Needed)

If something goes wrong, you can restore from backup:

```bash
# Restore from backup
mysql -u root -p sellapp_db < backup_before_cleanup_YYYYMMDD_HHMMSS.sql
```

## What Changed

### Code Changes
1. **CustomerController.php** - Lines 383-408, 410-434
   - Optimized duplicate checking using direct database queries
   - Better error handling and messages

2. **Customer.php** - Added new method
   - `findByEmailInCompany()` for email uniqueness checking

3. **Database Schema**
   - Added unique constraint: `UNIQUE (company_id, phone_number)`

### Files Added
1. `database/cleanup_duplicate_customers.php` - Cleanup script
2. `database/add_unique_constraint_customers.sql` - Migration script
3. `database/DUPLICATE_CUSTOMERS_FIX.md` - This documentation

## Support

If you encounter any issues:
1. Check the error logs in `storage/logs/` or PHP error log
2. Verify database connection settings in `config/database.php`
3. Ensure you have proper MySQL permissions
4. Make sure you ran the cleanup script before adding the constraint

## Notes

- The cleanup script is **transactional** - if any error occurs, all changes are rolled back
- The constraint only applies to **non-null** and **non-empty** phone numbers
- Empty or NULL phone numbers are still allowed (for walk-in customers)
- The fix preserves all related data (sales, repairs, swaps) during cleanup

