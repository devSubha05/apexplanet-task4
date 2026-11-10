<?php
// Visit: http://localhost:8000/
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

// Auto-heal misplaced static assets from section sub-paths (e.g. /delivery/assets/... -> /assets/...)
if (preg_match('#^/(?:admin|shop|delivery)/(assets|uploads)/(.*)$#', $uri, $m)) {
    $staticTarget = __DIR__ . '/' . $m[1] . '/' . $m[2];
    if (is_file($staticTarget)) {
        header('Location: /' . $m[1] . '/' . $m[2], true, 302);
        return true;
    }
}

// Auto-heal duplicate subfolder paths (e.g. /delivery/delivery/... -> /delivery/...)
if (preg_match('#^/(admin|shop|delivery)/\1/(.*)$#', $uri, $m)) {
    header('Location: /' . $m[1] . '/' . $m[2], true, 302);
    return true;
}

if (file_exists(__DIR__ . '/includes/db.php')) {
    require_once __DIR__ . '/includes/db.php';
    if (function_exists('ensure_mariadb_running')) {
        ensure_mariadb_running();
    }
}

if ($uri === '/shop' || $uri === '/shop/') {
    require __DIR__ . '/shop/index.php';
    return true;
}

if ($uri === '/delivery' || $uri === '/delivery/') {
    require __DIR__ . '/delivery/index.php';
    return true;
}

if ($uri === '/admin' || $uri === '/admin/') {
    require __DIR__ . '/admin/index.php';
    return true;
}

if (is_dir($file) && is_file($file . '/index.php')) {
    require $file . '/index.php';
    return true;
}

if (is_file($file . '.php')) {
    require $file . '.php';
    return true;
}

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($uri, ENT_QUOTES, 'UTF-8');
return true;

