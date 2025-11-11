-- Run SMS Pricing System Migrations
-- Execute these migrations in order:

-- 1. Create vendor plans table
SOURCE database/migrations/create_sms_vendor_plans_table.sql;

-- 2. Create company pricing table
SOURCE database/migrations/create_company_sms_pricing_table.sql;

-- 3. Update sms_payments table for Paystack
SOURCE database/migrations/update_sms_payments_for_paystack.sql;

-- 4. Seed vendor plans data
SOURCE database/migrations/seed_sms_vendor_plans.sql;

-- Alternative: If SOURCE doesn't work, run each SQL file manually in order:
-- 1. create_sms_vendor_plans_table.sql
-- 2. create_company_sms_pricing_table.sql
-- 3. update_sms_payments_for_paystack.sql
-- 4. seed_sms_vendor_plans.sql

