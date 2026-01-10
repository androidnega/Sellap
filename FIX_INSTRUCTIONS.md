# Fix Instructions for Audit Trail Profit Issue

## Problem
The audit trail is showing:
- Revenue: ₵33,430.00 ✅ (correct)
- Cost: ₵138,263.00 ❌ (WRONG - inflated due to SQL bug)
- Net Profit: ₵0.00 ❌ (should be positive)

## Root Cause
The cost calculation SQL query had a nested `SUM()` within `SUM()` that was multiplying costs incorrectly.

## Solution
The fix has been committed to Git. You need to pull it to your server.

## Steps to Fix

### On Your Server (via cPanel Terminal or SSH):

```bash
cd /home3/manuelc8/sellapp.store
git pull origin master
```

### If git pull shows conflicts or doesn't work:

```bash
cd /home3/manuelc8/sellapp.store
git stash  # Save any local changes
git pull origin master
```

### After pulling, refresh your browser:
1. Go to: https://sellapp.store/dashboard/audit-trail
2. Hard refresh: `Ctrl + Shift + R` (Windows) or `Cmd + Shift + R` (Mac)
3. Click "This Month" button to load monthly data
4. Check the profit - it should now show correctly!

## What Was Fixed

### Files Updated:
1. **app/Services/AnalyticsService.php** - Fixed the `getProfitStats()` SQL query
2. **app/Controllers/ManagerAnalyticsController.php** - Set monthly as default (not today)
3. **app/Controllers/DashboardController.php** - Set monthly as default for dashboard
4. **app/Views/components/sidebar.php** - Removed duplicate audit trail link

### Expected Result After Fix:
- ✅ Revenue: ₵33,430.00 (unchanged)
- ✅ Cost: ~₵25,000.00 (corrected - will be realistic now)
- ✅ Net Profit: ~₵8,000.00+ (will show actual profit!)

## Note
The "Period: Today" label appears when you click the "Today" button. 
By default, it should show "This Month" which is the new default setting.

