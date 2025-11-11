# Network Setup Guide - Access from Local Network

## Overview
This guide will help you configure SellApp to be accessible from other computers on your local network using IP address `192.168.33.85`.

## Step 1: Configure XAMPP Apache to Listen on Your Network IP

1. **Open XAMPP Control Panel**
   - Make sure Apache is stopped

2. **Edit Apache Configuration**
   - Open `C:\xampp\apache\conf\httpd.conf` in a text editor (as Administrator)
   - Find the line: `Listen 80` or `Listen 127.0.0.1:80`
   - Change it to: `Listen 192.168.33.85:80`
   - OR add a new line: `Listen 192.168.33.85:80` (keep the original Listen 80 for localhost)

3. **Find and Update ServerName (Optional but Recommended)**
   - Find the line: `#ServerName www.example.com:80`
   - Add or uncomment: `ServerName 192.168.33.85:80`

4. **Check Virtual Hosts (if any)**
   - If you have virtual hosts configured, ensure they allow access from your network IP
   - Look for `<Directory>` directives and ensure they allow access

5. **Save and Restart Apache**
   - Save the httpd.conf file
   - Start Apache from XAMPP Control Panel

## Step 2: Configure Windows Firewall

1. **Open Windows Firewall**
   - Press `Win + R`, type `wf.msc`, press Enter

2. **Add Inbound Rule**
   - Click "Inbound Rules" → "New Rule"
   - Select "Port" → Next
   - Select "TCP" and enter port `80` → Next
   - Select "Allow the connection" → Next
   - Check all profiles (Domain, Private, Public) → Next
   - Name: "Apache HTTP Server" → Finish

## Step 3: Verify Network IP Address

1. **Check Your IP Address**
   - Open Command Prompt
   - Run: `ipconfig`
   - Verify your IP is `192.168.33.85`
   - If different, update the IP in Step 1

## Step 4: Test Access

1. **From the Server Computer:**
   - Open browser: `http://192.168.33.85/sellapp`

2. **From Another Computer on Network:**
   - Open browser: `http://192.168.33.85/sellapp`
   - Make sure both computers are on the same network

## Step 5: Update Application Configuration (Optional)

The application should auto-detect the base path. If you need to hardcode it:

1. Edit `config/app.php`
2. Update `BASE_URL_PATH` if needed (usually auto-detection works)

## Troubleshooting

### Cannot Access from Other Computers
- Check Windows Firewall is allowing port 80
- Verify both computers are on the same network
- Check that Apache is running
- Try accessing `http://192.168.33.85` (without /sellapp) first

### Apache Won't Start
- Check if port 80 is already in use
- Run XAMPP Control Panel as Administrator
- Check Apache error logs: `C:\xampp\apache\logs\error.log`

### Connection Refused
- Verify IP address is correct: `ipconfig`
- Check firewall settings
- Ensure Apache is listening on the correct IP: Check httpd.conf

## Security Notes

⚠️ **Important Security Reminders:**
- This configuration exposes your application to your local network
- Ensure your database password is strong
- Consider using HTTPS for production
- Don't use this configuration on public networks

## Quick Configuration Script

Run this PowerShell script as Administrator to configure Apache:

```powershell
# Get your IP address
$ip = "192.168.33.85"
$confPath = "C:\xampp\apache\conf\httpd.conf"

# Backup original config
Copy-Item $confPath "$confPath.backup"

# Read config
$content = Get-Content $confPath

# Add Listen directive if not exists
if ($content -notmatch "Listen $ip") {
    $content = $content | ForEach-Object {
        if ($_ -match "^Listen\s+80$") {
            $_ + "`nListen $ip:80"
        } else {
            $_
        }
    }
    $content | Set-Content $confPath
    Write-Host "Apache configured to listen on $ip:80"
} else {
    Write-Host "Apache already configured for $ip"
}
```

