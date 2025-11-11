# SMS Dynamic Pricing & Paystack Integration Implementation

This document outlines the implementation of dynamic SMS pricing and Paystack payment integration for SellApp.

## Overview

The system now supports:
- **Dynamic SMS bundle pricing** based on vendor plans with configurable markup
- **Paystack payment integration** for SMS credit purchases
- **Backward compatibility** with existing PayPal integration

## Database Schema

### New Tables

1. **sms_vendor_plans** - Stores vendor SMS bundles
   - `id`, `vendor_name`, `label`, `cost_amount`, `messages`, `expires_in_days`, `meta`

2. **company_sms_pricing** - Stores dynamic pricing per company
   - `id`, `company_id`, `vendor_plan_id`, `markup_percent`, `custom_price`, `active`

### Updated Tables

3. **sms_payments** - Updated to support Paystack
   - Added: `vendor_plan_id`, `paystack_reference`, `user_id`, `company_price`
   - Updated: `payment_provider` enum to include 'paystack'
   - Updated: `status` enum to include 'initiated'

## Migration Steps

Run the following migrations in order:

```bash
# Option 1: Run the combined migration file
mysql -u root -p sellapp_db < database/migrations/run_sms_pricing_migrations.sql

# Option 2: Run each migration individually
mysql -u root -p sellapp_db < database/migrations/create_sms_vendor_plans_table.sql
mysql -u root -p sellapp_db < database/migrations/create_company_sms_pricing_table.sql
mysql -u root -p sellapp_db < database/migrations/update_sms_payments_for_paystack.sql
mysql -u root -p sellapp_db < database/migrations/seed_sms_vendor_plans.sql
```

## API Endpoints

### Get Available Bundles
```
GET /api/sms/pricing
```
Returns all available SMS bundles with computed pricing for the company.

### Get Specific Bundle Pricing
```
GET /api/sms/pricing/bundle?plan=1&company=5
```
Returns pricing details for a specific bundle.

### Initiate Paystack Payment
```
POST /api/sms/paystack/initiate
Body: {
    "vendor_plan_id": 1,
    "email": "customer@example.com"
}
```

### Verify Payment
```
GET /api/sms/paystack/verify?reference=xxx
or
GET /api/sms/paystack/callback?payment_id=xxx
```

### Webhook (Paystack)
```
POST /api/sms/paystack/webhook
```
Paystack will call this endpoint with payment events.

## Pricing Logic

1. **Vendor Cost Per SMS** = `vendor_cost / messages`
2. **Markup Factor** = `1 + (markup_percent / 100)`
3. **Company Price** = `vendor_cost × markup_factor` (or `custom_price` if set)
4. **Unit Price** = `company_price / messages`
5. **Profit** = `company_price - vendor_cost`

## Default Markup

- Default markup: **90%** (1.9× multiplier)
- This can be customized per company and per bundle

## Vendor Plans Seeded

The following Arkasel bundles are pre-seeded:
- GHS20 - 645 messages (No Expiry)
- GHS50 - 1,667 messages
- GHS100 - 3,448 messages
- GHS200 - 7,905 messages (Expires in 30 days)
- GHS500 - 20,704 messages (Expires in 30 days)
- GHS1,000 - 43,478 messages
- GHS2,000 - 99,533 messages
- GHS200 - 7,143 messages (No Expiry)
- GHS500 - 18,519 messages (No Expiry)

## Configuration

Ensure Paystack credentials are configured in `system_settings`:
- `paystack_secret_key`
- `paystack_public_key`
- `paystack_mode` (test/live)

## Example Usage Flow

1. Manager selects SMS bundle (e.g., 645 messages for GHS 20 vendor cost)
2. System computes company price (e.g., GHS 38 with 90% markup)
3. Manager initiates payment → `POST /api/sms/paystack/initiate`
4. Frontend redirects to Paystack payment page
5. Customer completes payment
6. Paystack calls webhook → `POST /api/sms/paystack/webhook`
7. System verifies payment and credits SMS to company account

## Backward Compatibility

- Existing PayPal integration remains functional
- Old `POST /api/payments/sms/initiate` endpoint still works
- Payment history includes both PayPal and Paystack transactions

## Files Created/Modified

### New Files
- `app/Models/SmsVendorPlan.php`
- `app/Models/CompanySmsPricing.php`
- `app/Services/PaystackService.php`
- `app/Services/SmsPricingService.php`
- `database/migrations/create_sms_vendor_plans_table.sql`
- `database/migrations/create_company_sms_pricing_table.sql`
- `database/migrations/update_sms_payments_for_paystack.sql`
- `database/migrations/seed_sms_vendor_plans.sql`

### Modified Files
- `app/Controllers/PaymentController.php` - Added Paystack methods
- `routes/web.php` - Added new API routes

