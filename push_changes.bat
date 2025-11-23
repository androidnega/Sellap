@echo off
cd C:\xampp\htdocs\sellapp
git add fix_technician_redirect_live.php test_technician_dashboard.php test_technician_redirect_monitor.php
git commit -m "Fix dashboard URLs and database connection in diagnostic tools"
git push
pause

