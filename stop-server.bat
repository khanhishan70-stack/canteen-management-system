@echo off
title Campus Canteen - stop server
echo.
echo  Stopping the canteen servers...
taskkill /F /IM httpd.exe  >nul 2>&1
taskkill /F /IM mysqld.exe >nul 2>&1
echo  Done. Website and database are stopped.
echo.
pause
