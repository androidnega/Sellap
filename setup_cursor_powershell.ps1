# Setup script to add Cursor Agent compatibility to PowerShell profile
# Run this script in PowerShell: .\setup_cursor_powershell.ps1

Write-Host "Setting up Cursor Agent PowerShell compatibility..." -ForegroundColor Cyan

# Get the profile path
$profilePath = $PROFILE

# Check if profile exists
if (-not (Test-Path $profilePath)) {
    Write-Host "Creating PowerShell profile at: $profilePath" -ForegroundColor Yellow
    $profileDir = Split-Path $profilePath -Parent
    if (-not (Test-Path $profileDir)) {
        New-Item -ItemType Directory -Path $profileDir -Force | Out-Null
    }
    New-Item -ItemType File -Path $profilePath -Force | Out-Null
}

# Check if configuration already exists
$profileContent = Get-Content $profilePath -Raw -ErrorAction SilentlyContinue
if ($profileContent -and $profileContent -match "CURSOR_AGENT") {
    Write-Host "Cursor Agent configuration already exists in profile." -ForegroundColor Yellow
    Write-Host "Skipping installation. If you want to reinstall, remove the CURSOR_AGENT section from your profile first." -ForegroundColor Yellow
    exit
}

# Configuration to add
$config = @"

# Cursor Agent Compatibility Configuration
# This simplifies the prompt when Cursor Agent is running to avoid terminal output issues
if (`$env:CURSOR_AGENT) {
    # Use a simple prompt when Cursor Agent is running
    function prompt {
        `$pwd = (Get-Location).Path
        `$user = `$env:USERNAME
        "`$user `$pwd> "
    }
    
    # Disable fancy prompts/themes that might interfere
    # If you use Oh My Posh, Starship, or similar, they will be skipped when CURSOR_AGENT is set
}

"@

# Append to profile
Add-Content -Path $profilePath -Value $config

Write-Host "Successfully added Cursor Agent configuration to your PowerShell profile!" -ForegroundColor Green
Write-Host "Profile location: $profilePath" -ForegroundColor Cyan
Write-Host ""
Write-Host "Please restart Cursor for the changes to take effect." -ForegroundColor Yellow

