@echo off
REM Setup script to add Cursor Agent compatibility to PowerShell profile
REM Run this script by double-clicking it or running: setup_cursor_powershell.bat

echo Setting up Cursor Agent PowerShell compatibility...
echo.

REM Get the PowerShell profile path
for /f "delims=" %%i in ('powershell -NoProfile -Command "Write-Host $PROFILE"') do set PROFILE_PATH=%%i

echo Profile location: %PROFILE_PATH%
echo.

REM Check if profile exists
if not exist "%PROFILE_PATH%" (
    echo Creating PowerShell profile...
    for %%F in ("%PROFILE_PATH%") do (
        if not exist "%%~dpF" mkdir "%%~dpF"
    )
    type nul > "%PROFILE_PATH%"
)

REM Check if configuration already exists
findstr /C:"CURSOR_AGENT" "%PROFILE_PATH%" >nul 2>&1
if %errorlevel% equ 0 (
    echo Cursor Agent configuration already exists in profile.
    echo Skipping installation. If you want to reinstall, remove the CURSOR_AGENT section from your profile first.
    pause
    exit /b
)

REM Create temporary file with configuration
set TEMP_FILE=%TEMP%\cursor_agent_config.ps1
(
echo.
echo # Cursor Agent Compatibility Configuration
echo # This simplifies the prompt when Cursor Agent is running to avoid terminal output issues
echo if ($env:CURSOR_AGENT^) {
echo     # Use a simple prompt when Cursor Agent is running
echo     function prompt {
echo         $pwd = (Get-Location^).Path
echo         $user = $env:USERNAME
echo         "$user $pwd> "
echo     }
echo.
echo     # Disable fancy prompts/themes that might interfere
echo     # If you use Oh My Posh, Starship, or similar, they will be skipped when CURSOR_AGENT is set
echo }
echo.
) > "%TEMP_FILE%"

REM Append to profile
type "%TEMP_FILE%" >> "%PROFILE_PATH%"
del "%TEMP_FILE%"

echo.
echo Successfully added Cursor Agent configuration to your PowerShell profile!
echo.
echo Please restart Cursor for the changes to take effect.
echo.
pause

