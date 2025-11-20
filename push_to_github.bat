@echo off
echo Checking git status...
git status

echo.
echo Staging all changes...
git add .

echo.
echo Committing changes...
git commit -m "Update system: %date% %time%"

echo.
echo Pushing to GitHub...
git push origin main
if errorlevel 1 (
    git push origin master
)

echo.
echo Done!

