<?php
// router.php - Router script for PHP built-in web server:
// Usage: php -S localhost:8000 router.php
// Visit: http://localhost:8000/

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $uri;

// 1. If static file exists, let PHP built-in server serve it
if ($uri !== '/' && is_file($file)) {
    return false;
}

// 2. Direct folder routes
if ($uri === '/shop' || $uri === '/shop/') {
    require __DIR__ . '/shop/index.php';
    return true;
}

if ($uri === '/admin' || $uri === '/admin/') {
    require __DIR__ . '/admin/index.php';
    return true;
}

// 3. Subfolder index handling
if (is_dir($file) && is_file($file . '/index.php')) {
    require $file . '/index.php';
    return true;
}

// 4. Extensionless PHP requests (e.g. /shops -> shops.php, /cart -> cart.php)
if (is_file($file . '.php')) {
    require $file . '.php';
    return true;
}

// 5. Root request
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

// Default fallback to 404
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($uri, ENT_QUOTES, 'UTF-8');
return true;

