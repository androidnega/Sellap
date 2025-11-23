# Customer Display Issue - Manual Debugging Steps

## Step 1: Check Server Error Logs

1. Navigate to: `C:\xampp\htdocs\sellapp\storage\logs\`
2. Open the latest error log file
3. Look for these log entries (search for "CustomerController" or "Customer::getPaginated"):
   - `CustomerController: DIRECT DB CHECK - Total customers in database`
   - `Customer::getPaginated - Customer details`
   - `Customers View: Customer details`
   - `Customers View: Total customers displayed in table`

**What to look for:**
- Does the database check show 2 customers?
- Does the query return 2 customers?
- Does the view receive 2 customers?
- Does the view display 2 customers?

## Step 2: Check Browser Console

1. Open the customers page in your browser
2. Press F12 to open Developer Tools
3. Go to the "Console" tab
4. Look for these messages:
   - `Customers page loaded: Cleared all filters, showing all customers`
   - `Customers page loaded: Total rows in DOM: X, Visible rows: Y`

**What to look for:**
- Are there 2 rows in the DOM?
- Are both rows visible?
- Are there any JavaScript errors (red text)?

## Step 3: Verify Database Directly

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Select your database
3. Run this query (replace YOUR_COMPANY_ID with your actual company ID):

```sql
SELECT id, full_name, phone_number, company_id, created_at 
FROM customers 
WHERE company_id = YOUR_COMPANY_ID 
ORDER BY created_at DESC;
```

**What to look for:**
- Do both customers exist in the database?
- Do they have the same company_id?
- What are their created_at timestamps?

## Step 4: Test Customer Creation Flow

1. Clear browser cache (Ctrl + Shift + R)
2. Go to customers page
3. Create first customer "Test1"
4. Note the URL after redirect
5. Create second customer "Test2"
6. Note the URL after redirect
7. Check how many customers are displayed

**What to look for:**
- What is the URL after each creation?
- Does it have any unexpected parameters?
- How many customers show after each creation?

## Step 5: Check for Hidden Filters

1. On the customers page, check:
   - Is the "Show duplicates only" checkbox checked?
   - Is there any text in the search box?
   - Is the date filter set to anything other than "All Time"?

2. If any of these are set, clear them and refresh

## Step 6: Inspect HTML

1. Right-click on the customer table
2. Select "Inspect" or "Inspect Element"
3. Look for the `<tbody id="customersTableBody">` element
4. Count how many `<tr>` elements are inside it

**What to look for:**
- Are there 2 `<tr>` elements (one for each customer)?
- Do any have `style="display: none"`?
- Do any have the `hidden` class?

## What to Report Back

Please share:
1. Server log output (especially the DIRECT DB CHECK and query results)
2. Browser console output (especially the row counts)
3. Database query results (how many customers exist)
4. URL after creating customers
5. Any JavaScript errors from console

This will help identify exactly where customers are being lost!

