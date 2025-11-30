# Cursor Agent PowerShell Profile Configuration
# Add this to your PowerShell profile to fix terminal output issues
# 
# To add this to your profile:
# 1. Open PowerShell and run: notepad $PROFILE
# 2. Copy the content below into your profile file
# 3. Save and restart PowerShell/Cursor

# Check if Cursor Agent is running
if ($env:CURSOR_AGENT) {
    # Use a simple prompt when Cursor Agent is running
    function prompt {
        $pwd = (Get-Location).Path
        $user = $env:USERNAME
        "$user $pwd> "
    }
    
    # Disable any custom themes or modules that might interfere
    # If you're using Oh My Posh, Starship, or similar, disable them here
    # Example:
    # if (Get-Command oh-my-posh -ErrorAction SilentlyContinue) {
    #     # Skip Oh My Posh initialization
    # }
} else {
    # Your normal prompt configuration goes here
    # This section only runs when Cursor Agent is NOT active
}

