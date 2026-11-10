<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect(string $p): void { header('Location: ' . url($p)); exit; }
function money($n): string { return '₹' . number_format((float)$n, 2); }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function get_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) { http_response_code(419); exit('Invalid or expired form token. Go back and try again.'); }
}

function current_user(): ?array {
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $s = db()->prepare('SELECT id,name,email,phone,address,role,status,avatar FROM users WHERE id=?');
            $s->execute([$_SESSION['uid']]);
            $u = $s->fetch() ?: null;
            if ($u && $u['status'] === 'blocked') { $u = null; session_destroy(); }
        }
    }
    return $u;
}
function require_login(): array {
    $u = current_user();
    if (!$u) { flash('warning', 'Please log in to continue.'); redirect('login.php'); }
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('403 - Admins only.'); }
    return $u;
}
function require_shop_owner(): array {
    $u = require_login();
    if ($u['role'] !== 'shop_owner' && $u['role'] !== 'admin') { http_response_code(403); exit('403 - Shop owners and admins only.'); }
    return $u;
}
function require_delivery(): array {
    $u = require_login();
    if ($u['role'] !== 'delivery' && $u['role'] !== 'admin') {
        http_response_code(403);
        exit('403 - Delivery staff and admins only.');
    }
    return $u;
}
function owner_shop(int $uid): ?array {
    $s = db()->prepare('SELECT * FROM shops WHERE owner_id=?');
    $s->execute([$uid]);
    $shop = $s->fetch();
    if (!$shop) {
        $u = current_user();
        if ($u && $u['role'] === 'admin') {
            $first = db()->query('SELECT * FROM shops ORDER BY id ASC LIMIT 1')->fetch();
            if ($first) return $first;
        }
        return null;
    }
    return $shop;
}
function login_user(array $row): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $row['id'];
    db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$row['id']]);
}

function valid_password(string $p): ?string {
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $p) || !preg_match('/[a-z]/', $p) || !preg_match('/\d/', $p))
        return 'Password needs an uppercase letter, a lowercase letter and a number.';
    return null;
}
function valid_phone(string $p): bool { return (bool)preg_match('/^[0-9]{10}$/', $p); }

function save_image(array $file, string $dir): array {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, 'Upload failed. Try again.'];
    if ($file['size'] > MAX_UPLOAD_BYTES) return [null, 'Image must be 2 MB or smaller.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || !getimagesize($file['tmp_name'])) return [null, 'Only JPG, PNG or WEBP images are allowed.'];
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $target = __DIR__ . '/../uploads/' . $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) return [null, 'Could not save the image.'];
    return [$name, null];
}
function delete_image(?string $name, string $dir): void {
    if ($name && preg_match('/^[a-f0-9]+\.(jpg|png|webp)$/', $name)) @unlink(__DIR__ . '/../uploads/' . $dir . '/' . $name);
}
function avatar_url(?array $u): string {
    return ($u && $u['avatar']) ? url('uploads/avatars/' . $u['avatar'])
        : 'https://ui-avatars.com/api/?background=c8102e&color=fff&name=' . urlencode($u['name'] ?? 'U');
}
function food_img(?string $f): string { return $f ? url('uploads/foods/' . $f) : url('assets/img-placeholder.svg'); }

function cart(): array { return $_SESSION['cart'] ?? []; }
function cart_count(): int { return array_sum(cart()); }

function paginate(int $total, int $page, int $per, string $baseQuery = ''): string {
    $pages = (int)ceil($total / $per);
    if ($pages <= 1) return '';

    $h = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center flex-wrap shadow-sm rounded-pill p-1 bg-white d-inline-flex border">';

    // Previous Arrow (shown when on page > 1)
    if ($page > 1) {
        $prev = $page - 1;
        $h .= '<li class="page-item"><a class="page-link" href="?' . $baseQuery . 'page=' . $prev . '" aria-label="Previous" title="Previous page"><span aria-hidden="true">&laquo;</span></a></li>';
    }

    // Determine window: show 1, 2, 3 on first pages then slide as user moves forward
    if ($pages <= 3) {
        $start = 1;
        $end = $pages;
    } else {
        if ($page <= 3) {
            $start = 1;
            $end = 3;
        } elseif ($page >= $pages) {
            $start = max(1, $pages - 2);
            $end = $pages;
        } else {
            $start = $page - 1;
            $end = min($pages, $page + 1);
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i === $page) ? ' active' : '';
        $h .= '<li class="page-item' . $active . '"><a class="page-link" href="?' . $baseQuery . 'page=' . $i . '">' . $i . '</a></li>';
    }

    // Next Arrow (shown when more pages exist ahead)
    if ($page < $pages) {
        $next = $page + 1;
        $h .= '<li class="page-item"><a class="page-link" href="?' . $baseQuery . 'page=' . $next . '" aria-label="Next" title="Next page"><span aria-hidden="true">&raquo;</span></a></li>';
    }

    $h .= '</ul></nav>';
    return $h;
}
