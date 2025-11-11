@echo off
REM Network Setup Script for SellApp
REM This script helps configure XAMPP to be accessible from local network

echo ========================================
echo SellApp Network Setup
echo ========================================
echo.
echo This script will help you configure XAMPP Apache
echo to be accessible from your local network at 192.168.33.85
echo.
echo IMPORTANT: Run this script as Administrator!
echo.

pause

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: This script must be run as Administrator!
    echo Right-click and select "Run as administrator"
    pause
    exit /b 1
)

set APACHE_CONF=C:\xampp\apache\conf\httpd.conf
set IP_ADDRESS=192.168.33.85

echo Checking Apache configuration...
if not exist "%APACHE_CONF%" (
    echo ERROR: Apache configuration not found at %APACHE_CONF%
    echo Please update the APACHE_CONF path in this script
    pause
    exit /b 1
)

echo.
echo Creating backup of httpd.conf...
copy "%APACHE_CONF%" "%APACHE_CONF%.backup.%date:~-4,4%%date:~-10,2%%date:~-7,2%" >nul
echo Backup created.

echo.
echo Checking if Apache is already configured for %IP_ADDRESS%...
findstr /C:"Listen %IP_ADDRESS%" "%APACHE_CONF%" >nul
if %errorLevel% equ 0 (
    echo Apache is already configured to listen on %IP_ADDRESS%
    echo.
) else (
    echo Adding Listen directive for %IP_ADDRESS%...
    REM This is a simple approach - you may need to edit manually
    echo.
    echo Please manually edit: %APACHE_CONF%
    echo.
    echo Find the line with: Listen 80
    echo Add or modify to: Listen %IP_ADDRESS%:80
    echo.
    echo Or add a new line: Listen %IP_ADDRESS%:80
    echo.
)

echo.
echo ========================================
echo Next Steps:
echo ========================================
echo 1. Edit %APACHE_CONF%
echo 2. Find "Listen 80" and add "Listen %IP_ADDRESS%:80"
echo 3. Find "ServerName" and set to "ServerName %IP_ADDRESS%:80"
echo 4. Restart Apache from XAMPP Control Panel
echo 5. Configure Windows Firewall to allow port 80
echo.
echo For detailed instructions, see NETWORK_SETUP.md
echo.
pause

