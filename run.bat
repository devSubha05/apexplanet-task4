@echo off
title Rasoi Express Launcher
cd /d "%~dp0"

echo ========================================================
echo Starting Rasoi Express (Database + Web Server)...
echo ========================================================

netstat -ano | findstr :3306 | findstr LISTENING >nul 2>&1
if %errorlevel% neq 0 (
    echo Starting MariaDB Server on port 3306...
    start "MariaDB Server" cmd /k "cd /d \"C:\Program Files\MariaDB 13.0\" && \"C:\Program Files\MariaDB 13.0\bin\mysqld.exe\" --defaults-file=\"C:\Program Files\MariaDB 13.0\data\my.ini\" --console"
    timeout /t 3 /nobreak >nul
) else (
    echo [OK] MariaDB is already running on port 3306.
)

echo.
echo ========================================================
echo Web Server running at: http://localhost:8000/
echo Keep this window open while using the website!
echo Press Ctrl+C to stop.
echo ========================================================
echo.

php -S localhost:8000 router.php
pause

