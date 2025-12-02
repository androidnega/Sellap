# Verification script - check if Cursor Agent config was added
Write-Host "Checking PowerShell profile configuration..." -ForegroundColor Cyan
Write-Host ""

$profilePath = $PROFILE
Write-Host "Profile location: $profilePath" -ForegroundColor Yellow

if (Test-Path $profilePath) {
    Write-Host "Profile exists: YES" -ForegroundColor Green
    $content = Get-Content $profilePath -Raw
    
    if ($content -match "CURSOR_AGENT") {
        Write-Host "Cursor Agent configuration found: YES" -ForegroundColor Green
        Write-Host ""
        Write-Host "Configuration snippet:" -ForegroundColor Cyan
        $lines = Get-Content $profilePath
        $inConfig = $false
        foreach ($line in $lines) {
            if ($line -match "CURSOR_AGENT") { $inConfig = $true }
            if ($inConfig) {
                Write-Host $line -ForegroundColor Gray
                if ($line -match "^\s*}\s*$" -and $inConfig) { break }
            }
        }
    } else {
        Write-Host "Cursor Agent configuration found: NO" -ForegroundColor Red
        Write-Host "You need to run setup_cursor_powershell.ps1 first" -ForegroundColor Yellow
    }
} else {
    Write-Host "Profile exists: NO" -ForegroundColor Red
    Write-Host "Profile will be created when you run the setup script" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")


