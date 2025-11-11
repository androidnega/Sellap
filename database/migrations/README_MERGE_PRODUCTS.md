# Product Tables Merge Migration

## Overview
This migration consolidates the two product tables (`products` and `products_new`) into a single unified `products` table.

## What This Migration Does

1. **Merges Data**: Migrates all products from the old `products` table to `products_new`
   - Maps category names (VARCHAR) to category_ids (INT)
   - Maps brand names (VARCHAR) to brand_ids (INT)
   - Converts `qty` to `quantity`
   - Converts uppercase status values to lowercase
   - Skips duplicates (products with same name and company_id)

2. **Renames Tables**:
   - `products_new` → `products` (becomes the main table)
   - `products` → `products_old_backup` (safety backup)

3. **Updates All Code**: All Product model methods now use the unified `products` table

## Before Running Migration

⚠️ **IMPORTANT**: 
- **Backup your database** before running this migration
- Verify that all products are correctly migrated
- The backup table `products_old_backup` will be created - you can drop it later after verification

## How to Run

1. **Backup your database first**:
   ```bash
   mysqldump -u root -p sellapp_db > backup_before_merge.sql
   ```

2. **Run the migration SQL**:
   - Via MySQL command line:
     ```bash
     mysql -u root -p sellapp_db < database/migrations/merge_products_tables.sql
     ```
   - Or via phpMyAdmin: Open the SQL tab and paste the contents of `merge_products_tables.sql`

3. **Verify the migration**:
   - Check that product counts match (old + new = merged)
   - Test that products display correctly in inventory
   - Verify edit/view functionality works

4. **After verification** (optional):
   - Drop the backup table:
     ```sql
     DROP TABLE IF EXISTS products_old_backup;
     ```

## Notes

- Products with duplicate names in the same company will be skipped (only one will be migrated)
- Category and brand names must exist in the `categories` and `brands` tables for proper mapping
- If a category/brand doesn't exist, it will default to category_id=1 or brand_id=NULL

