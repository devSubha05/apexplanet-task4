@echo off
title MariaDB Server - Rasoi Express
cd /d "C:\Program Files\MariaDB 13.0"
echo ========================================================
echo Starting MariaDB Server on Port 3306...
echo Keep this window open while using the application.
echo ========================================================
"C:\Program Files\MariaDB 13.0\bin\mysqld.exe" --defaults-file="C:\Program Files\MariaDB 13.0\data\my.ini" --console
pause
