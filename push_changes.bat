@echo off
cd C:\xampp\htdocs\sellapp
git add app/Controllers/CustomerController.php app/Models/Customer.php app/Views/customers_index.php
git commit -m "Fix customer display - removed duplicate logic and duplicate filter"
git push
pause

