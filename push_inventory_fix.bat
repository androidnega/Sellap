@echo off
echo Adding inventory visibility fix for salespersons...
git add app/Models/Product.php
echo Committing changes...
git commit -m "Fix inventory visibility - salespersons can now see all items including quantity 0"
echo Pushing to remote...
git push
echo Done!
pause

