# Customer Creation Test Script

## Quick Test to Verify the Fix

Follow these steps exactly to test if the customer display issue is fixed:

---

## Step 1: Open Customer Management Page
1. Log in to your dashboard at `https://sellapp.store/dashboard`
2. Navigate to **Customers** page
3. Open the browser console (Press F12, then click on the "Console" tab)

---

## Step 2: Create First Customer
1. Click **"Add Customer"** button
2. Fill in the form:
   - **Full Name**: `Test Customer 1`
   - **Phone Number**: `0501111111`
   - **Email** (optional): `test1@example.com`
3. Click **"Create Customer"**
4. Wait for the page to reload

### ✅ Expected Result:
- You should see "Customer created successfully!" notification
- The customer table should show **Test Customer 1**
- The table should have **1 row**

### 📝 What to Check in Logs:
Open the browser console and you should see:
```
Customer created successfully: {data object}
```

---

## Step 3: Create Second Customer
1. Click **"Add Customer"** button again
2. Fill in the form:
   - **Full Name**: `Test Customer 2`
   - **Phone Number**: `0502222222`
   - **Email** (optional): `test2@example.com`
3. Click **"Create Customer"**
4. Wait for the page to reload

### ✅ Expected Result:
- You should see "Customer created successfully!" notification
- The customer table should show **BOTH Test Customer 1 AND Test Customer 2**
- The table should have **2 rows**
- **Test Customer 1 should NOT disappear** ← This is the key fix!

### ❌ If This Fails:
- If you only see Test Customer 2, the fix didn't work
- Check server error logs immediately (see instructions below)

---

## Step 4: Create Third Customer
1. Click **"Add Customer"** button again
2. Fill in the form:
   - **Full Name**: `Test Customer 3`
   - **Phone Number**: `0503333333`
   - **Email** (optional): `test3@example.com`
3. Click **"Create Customer"**
4. Wait for the page to reload

### ✅ Expected Result:
- The customer table should show **ALL THREE customers**
- The table should have **3 rows**
- Order should be: Test Customer 3, Test Customer 2, Test Customer 1 (newest first)

---

## Step 5: Test Customer Deletion
1. Find **Test Customer 2** in the table
2. Click the **Delete** button (trash icon) for Test Customer 2
3. Confirm the deletion
4. Wait for the page to reload

### ✅ Expected Result:
- Test Customer 2 should be completely gone
- Only Test Customer 3 and Test Customer 1 should remain
- The table should have **2 rows**
- NO duplicates should appear
- NO customer should become hidden

---

## Step 6: Check Server Logs

### For XAMPP Users:
1. Open your XAMPP Control Panel
2. Click "Logs" button next to Apache
3. Look for recent entries with these keywords:
   - `Customer::create - Attempting to create customer`
   - `Customer created successfully with ID:`
   - `CustomerController::store - About to create customer`
   - `CustomerController: DIRECT DB CHECK - Total customers in database`

### Expected Log Pattern:
For each customer creation, you should see something like:
```
CustomerController::store - About to create customer: Name=Test Customer 1, Phone=0501111111, Company=11
Customer::create - Attempting to create customer: Array(...)
Customer created successfully with ID: 123, unique_id: CUS..., name: Test Customer 1
CustomerController::store - Successfully retrieved customer ID: 123, Name: Test Customer 1
```

---

## Troubleshooting

### If customers still disappear:

1. **Check for JavaScript errors in console**
   - Open browser console (F12)
   - Look for red error messages
   - Take a screenshot and check the error

2. **Verify database**
   - Open phpMyAdmin
   - Check the `customers` table
   - Run this query: `SELECT id, full_name, phone_number, company_id, created_at FROM customers WHERE company_id = YOUR_COMPANY_ID ORDER BY created_at DESC`
   - Verify all customers are actually in the database

3. **Check server error logs**
   - Look for errors or warnings
   - Pay attention to any SQL errors
   - Check if there are permission issues

4. **Clear browser cache**
   - Sometimes cached JavaScript can cause issues
   - Try hard refresh: Ctrl+Shift+R (or Cmd+Shift+R on Mac)
   - Or open the page in an incognito/private window

---

## Success Criteria

✅ The fix is working if:
- [ ] Creating multiple customers shows ALL customers in the table
- [ ] No customer disappears when a new one is created
- [ ] Deleting a customer removes it completely (no duplicates appear)
- [ ] Browser console shows no errors
- [ ] Server logs show successful creation and retrieval for each customer

❌ The fix needs more work if:
- [ ] First customer disappears after creating second customer
- [ ] Customers appear as duplicates
- [ ] Deleting a customer causes weird behavior
- [ ] Errors appear in browser console or server logs

---

## Clean Up After Testing

Once you've verified everything works:

1. You can delete the test customers:
   - Delete Test Customer 1
   - Delete Test Customer 3
   
2. You can remove the debug logging if desired (optional):
   - Edit `app/Models/Customer.php` and remove the `error_log()` calls
   - Edit `app/Controllers/CustomerController.php` and remove the `error_log()` calls
   - Edit `app/Views/customers_index.php` and remove the `error_log()` calls

---

## Report Results

After testing, please report:
1. Which steps passed ✅
2. Which steps failed ❌
3. Any error messages from browser console
4. Any error messages from server logs
5. Screenshots if possible

This will help diagnose any remaining issues!

