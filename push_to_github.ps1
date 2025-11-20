# PowerShell script to push current system to GitHub
# Run this script from the project root directory

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Push SellApp to GitHub" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Change to script directory
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptPath

Write-Host "Current directory: $(Get-Location)" -ForegroundColor Gray
Write-Host ""

# Check if git is initialized
if (-not (Test-Path .git)) {
    Write-Host "Git not initialized. Initializing..." -ForegroundColor Yellow
    git init
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Failed to initialize git repository!" -ForegroundColor Red
        exit 1
    }
    Write-Host "Git repository initialized." -ForegroundColor Green
    Write-Host ""
}

# Check for remote
Write-Host "Checking for remote repository..." -ForegroundColor Cyan
$remote = git remote -v 2>$null
if (-not $remote) {
    Write-Host "No remote repository found!" -ForegroundColor Red
    Write-Host ""
    Write-Host "To add a GitHub remote, run:" -ForegroundColor Yellow
    Write-Host "  git remote add origin https://github.com/yourusername/your-repo.git" -ForegroundColor White
    Write-Host ""
    $addRemote = Read-Host "Would you like to add a remote now? (y/n)"
    if ($addRemote -eq 'y' -or $addRemote -eq 'Y') {
        $repoUrl = Read-Host "Enter your GitHub repository URL"
        if ($repoUrl) {
            git remote add origin $repoUrl
            if ($LASTEXITCODE -eq 0) {
                Write-Host "Remote added successfully!" -ForegroundColor Green
            } else {
                Write-Host "Failed to add remote." -ForegroundColor Red
                exit 1
            }
        }
    } else {
        exit 1
    }
} else {
    Write-Host "Current remotes:" -ForegroundColor Cyan
    git remote -v
}
Write-Host ""

# Get current branch
$branch = git branch --show-current 2>$null
if (-not $branch) {
    Write-Host "No branch found. Creating main branch..." -ForegroundColor Yellow
    $branch = "main"
    git checkout -b main 2>$null
    if ($LASTEXITCODE -ne 0) {
        $branch = "master"
        git checkout -b master 2>$null
    }
}

Write-Host "Current branch: $branch" -ForegroundColor Cyan
Write-Host ""

# Stage all changes
Write-Host "Staging all changes..." -ForegroundColor Cyan
git add .
Write-Host ""

# Check if there are changes to commit
$status = git status --porcelain
if (-not $status) {
    Write-Host "No changes to commit." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Checking if we need to push existing commits..." -ForegroundColor Cyan
    $localCommits = git log origin/$branch..HEAD 2>$null
    if ($localCommits) {
        Write-Host "Found local commits to push." -ForegroundColor Yellow
    } else {
        Write-Host "Everything is up to date!" -ForegroundColor Green
        exit 0
    }
} else {
    # Commit changes
    $commitMessage = "Update system: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    Write-Host "Committing changes..." -ForegroundColor Cyan
    Write-Host "Commit message: $commitMessage" -ForegroundColor Gray
    git commit -m $commitMessage
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Changes committed successfully!" -ForegroundColor Green
        Write-Host ""
    } else {
        Write-Host "Commit failed or no changes to commit." -ForegroundColor Yellow
        Write-Host ""
    }
}

# Push to GitHub
Write-Host "Pushing to GitHub..." -ForegroundColor Cyan
git push origin $branch

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "Successfully pushed to GitHub!" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "Push failed. Trying to set upstream..." -ForegroundColor Yellow
    git push -u origin $branch
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Successfully pushed to GitHub with upstream set!" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "Push failed. Possible reasons:" -ForegroundColor Red
        Write-Host "  - Authentication required (check your GitHub credentials)" -ForegroundColor Yellow
        Write-Host "  - Branch name mismatch (try: git push -u origin $branch)" -ForegroundColor Yellow
        Write-Host "  - Network issues" -ForegroundColor Yellow
        exit 1
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Done!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan

