@echo off
echo Pushing to GitHub...
echo.

echo Staging all changes...
git add .

echo.
echo Committing changes...
git commit -m "Update system: %date% %time%"

echo.
echo Pushing to GitHub...
git push origin master
if errorlevel 1 (
    echo Trying main branch...
    git push origin main
)

echo.
echo Done!

