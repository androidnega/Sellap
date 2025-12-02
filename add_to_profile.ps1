# Quick script to add Cursor Agent config
$profilePath = $PROFILE
if (-not (Test-Path $profilePath)) {
    $profileDir = Split-Path $profilePath -Parent
    if (-not (Test-Path $profileDir)) { New-Item -ItemType Directory -Path $profileDir -Force | Out-Null }
    New-Item -ItemType File -Path $profilePath -Force | Out-Null
}
$config = @"

# Cursor Agent Compatibility Configuration
if (`$env:CURSOR_AGENT) {
    function prompt {
        `$pwd = (Get-Location).Path
        `$user = `$env:USERNAME
        "`$user `$pwd> "
    }
}

"@
Add-Content -Path $profilePath -Value $config
Write-Host "Done! Profile: $profilePath"



