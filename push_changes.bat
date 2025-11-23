@echo off
cd C:\xampp\htdocs\sellapp
git add app/Controllers/CustomerController.php app/Models/Customer.php app/Views/customers_index.php
git commit -m "Fix customer duplicate display - prevent double submission and remove duplicate rows"
git push
pause

