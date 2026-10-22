@echo off
title Campus Canteen Server
color 0A
echo.
echo  ================================================
echo    CAMPUS CANTEEN - starting your server...
echo  ================================================
echo.

REM --- 1) Website server (Apache) ---
netstat -ano | findstr /r ":80 .*LISTENING" >nul 2>&1
if %errorlevel%==0 (
  echo  [OK] Website server  - already running
) else (
  powershell -NoProfile -Command "Start-Process 'C:\xampp\apache\bin\httpd.exe' -WindowStyle Hidden"
  echo  [OK] Website server  - started
)

REM --- 2) Database (MySQL) ---
netstat -ano | findstr /r ":3306 .*LISTENING" >nul 2>&1
if %errorlevel%==0 (
  echo  [OK] Database        - already running
) else (
  powershell -NoProfile -Command "Start-Process 'C:\xampp\mysql\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\xampp\mysql\bin\my.ini' -WindowStyle Hidden"
  echo  [OK] Database        - started
)

timeout /t 2 >nul

REM --- 3) Find this PC's WiFi address ---
set MYIP=unknown
for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } | Select-Object -First 1).IPAddress"`) do set MYIP=%%i

echo.
echo  ================================================
echo   YOUR LIVE LINKS (share with everyone on WiFi)
echo  ================================================
echo.
echo   On THIS computer (laptop):
echo     http://localhost/canteen/
echo.
echo   This PC's WiFi address:  %MYIP%
echo.
echo     Student phone:   http://%MYIP%/canteen/student/
echo     Staff phone:     http://%MYIP%/canteen/staff/
echo     Chef phone:      http://%MYIP%/canteen/chef/
echo     Scan-in page:    http://%MYIP%/canteen/devices.php
echo.
echo  ================================================
echo   Press any key to open the website...
echo  ================================================
pause >nul
start http://localhost/canteen/
