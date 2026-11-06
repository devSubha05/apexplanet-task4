<?php
// Edit these for your local XAMPP/WAMP setup
define('DB_HOST', 'localhost');
define('DB_NAME', 'rasoi_express');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Rasoi Express');
// Auto-detect base URL (handles root, subfolders like /apexplanet-task4 or /rasoi automatically)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = preg_replace('#/(admin|shop|includes|config|assets|uploads)$#', '', $scriptDir);
$detectedBase = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') ? '' : rtrim($scriptDir, '/');
define('BASE_URL', $detectedBase);

define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);
define('PER_PAGE', 8);
