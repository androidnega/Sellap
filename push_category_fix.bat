@echo off
echo Adding sales history category fix...
git add app/Models/POSSale.php
echo Committing changes...
git commit -m "Fix sales history category column - display category names correctly"
echo Pushing to remote...
git push
echo Done!
pause

