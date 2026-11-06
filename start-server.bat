@echo off
title PHP Server - Rasoi Express
echo ========================================================
echo Starting Rasoi Express Web Server...
echo Visit: http://localhost:8000/
echo Keep this window open while using the application.
echo ========================================================
php -S localhost:8000 router.php
pause

