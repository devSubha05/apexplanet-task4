@echo off
title Install MariaDB Service - Rasoi Express

echo ==========================================================
echo Installing MariaDB as a permanent Windows Service...
echo NOTE: This script must be run as Administrator.
echo ==========================================================
echo.

"C:\Program Files\MariaDB 13.0\bin\mysqld.exe" --install MariaDB --defaults-file="C:\Program Files\MariaDB 13.0\data\my.ini"
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Installation failed.
    echo Please right-click this file or your terminal and choose "Run as administrator".
    echo.
    pause
    exit /b %errorlevel%
)

net start MariaDB
echo.
echo ==========================================================
echo MariaDB Service installed and started successfully!
echo It will now run automatically in the background on port 3306.
echo ==========================================================
pause
