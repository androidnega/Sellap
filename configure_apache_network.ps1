# PowerShell script to configure Apache for network access
# Run this script as Administrator

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "SellApp Network Configuration Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if running as administrator
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "ERROR: This script must be run as Administrator!" -ForegroundColor Red
    Write-Host "Right-click PowerShell and select 'Run as Administrator'" -ForegroundColor Yellow
    pause
    exit 1
}

$apacheConf = "C:\xampp\apache\conf\httpd.conf"
$networkIP = "192.168.33.85"

# Verify Apache config exists
if (-not (Test-Path $apacheConf)) {
    Write-Host "ERROR: Apache configuration not found at: $apacheConf" -ForegroundColor Red
    Write-Host "Please verify your XAMPP installation path." -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host "Found Apache configuration at: $apacheConf" -ForegroundColor Green
Write-Host ""

# Create backup
$backupPath = "$apacheConf.backup.$(Get-Date -Format 'yyyyMMdd_HHmmss')"
Write-Host "Creating backup: $backupPath" -ForegroundColor Yellow
Copy-Item $apacheConf $backupPath
Write-Host "Backup created successfully!" -ForegroundColor Green
Write-Host ""

# Read configuration
$content = Get-Content $apacheConf -Raw

# Check if already configured
if ($content -match "Listen\s+$networkIP") {
    Write-Host "Apache is already configured to listen on $networkIP" -ForegroundColor Green
    Write-Host ""
} else {
    Write-Host "Configuring Apache to listen on $networkIP..." -ForegroundColor Yellow
    
    # Check if there's a Listen 80 line
    if ($content -match "Listen\s+80\b") {
        # Add Listen directive after the existing Listen 80
        $content = $content -replace "(Listen\s+80\b)", "`$1`nListen $networkIP`:80"
        Write-Host "Added 'Listen $networkIP`:80' directive" -ForegroundColor Green
    } elseif ($content -match "Listen\s+127\.0\.0\.1:80") {
        # Add Listen directive after localhost
        $content = $content -replace "(Listen\s+127\.0\.0\.1:80)", "`$1`nListen $networkIP`:80"
        Write-Host "Added 'Listen $networkIP`:80' directive" -ForegroundColor Green
    } else {
        # Add at the beginning of Listen directives section (usually around line 60)
        $content = $content -replace "(^#Listen\s+12\.34\.56\.78:80)", "Listen $networkIP`:80`n`$1"
        if ($content -notmatch "Listen\s+$networkIP") {
            # If replacement didn't work, add after a common pattern
            $content = $content -replace "(^# Dynamic Shared Object)", "Listen $networkIP`:80`n`n`$1"
        }
        Write-Host "Added 'Listen $networkIP`:80' directive" -ForegroundColor Green
    }
    
    # Update ServerName if commented out
    if ($content -match "#ServerName\s+www\.example\.com:80") {
        $content = $content -replace "#ServerName\s+www\.example\.com:80", "ServerName $networkIP`:80"
        Write-Host "Updated ServerName to $networkIP`:80" -ForegroundColor Green
    }
    
    # Write back to file
    $content | Set-Content $apacheConf -Encoding UTF8
    Write-Host "Configuration updated successfully!" -ForegroundColor Green
    Write-Host ""
}

# Configure Windows Firewall
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Configuring Windows Firewall..." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$firewallRuleName = "Apache HTTP Server (SellApp)"
$existingRule = Get-NetFirewallRule -DisplayName $firewallRuleName -ErrorAction SilentlyContinue

if ($existingRule) {
    Write-Host "Firewall rule already exists: $firewallRuleName" -ForegroundColor Green
} else {
    Write-Host "Creating firewall rule for port 80..." -ForegroundColor Yellow
    try {
        New-NetFirewallRule -DisplayName $firewallRuleName `
            -Direction Inbound `
            -Protocol TCP `
            -LocalPort 80 `
            -Action Allow `
            -Profile Domain,Private,Public | Out-Null
        Write-Host "Firewall rule created successfully!" -ForegroundColor Green
    } catch {
        Write-Host "Warning: Could not create firewall rule automatically." -ForegroundColor Yellow
        Write-Host "Please manually create a firewall rule for port 80 (TCP)" -ForegroundColor Yellow
        Write-Host "Error: $_" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Configuration Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Open XAMPP Control Panel" -ForegroundColor White
Write-Host "2. Stop Apache if it's running" -ForegroundColor White
Write-Host "3. Start Apache again" -ForegroundColor White
Write-Host "4. Test access from this computer: http://$networkIP/sellapp" -ForegroundColor White
Write-Host "5. Test access from another computer: http://$networkIP/sellapp" -ForegroundColor White
Write-Host ""
Write-Host "If you encounter issues:" -ForegroundColor Yellow
Write-Host "- Check that your IP address is correct (run: ipconfig)" -ForegroundColor White
Write-Host "- Verify both computers are on the same network" -ForegroundColor White
Write-Host "- Check Apache error logs: C:\xampp\apache\logs\error.log" -ForegroundColor White
Write-Host ""
Write-Host "Backup location: $backupPath" -ForegroundColor Gray
Write-Host ""

pause

