<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('index.php');
$pageTitle = 'Log in';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pw = $_POST['password'] ?? '';

    // simple brute-force throttle: 5 failures -> 5 minute lock per session
    if (($_SESSION['fails'] ?? 0) >= 5 && time() < ($_SESSION['lock_until'] ?? 0)) {
        $error = 'Too many failed attempts. Try again in a few minutes.';
    } else {
        $s = db()->prepare('SELECT * FROM users WHERE email=?'); $s->execute([$email]);
        $u = $s->fetch();
        if ($u && password_verify($pw, $u['password_hash'])) {
            if ($u['status'] === 'blocked') {
                $error = 'This account has been blocked. Contact support.';
            } else {
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
                }
                unset($_SESSION['fails'], $_SESSION['lock_until']);
                login_user($u);
                flash('success', 'Welcome back, ' . ($u['name'] ?? 'user') . '!');
                redirect('index.php');
            }
        } else {
            $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
            if ($_SESSION['fails'] >= 5) $_SESSION['lock_until'] = time() + 300;
            $error = 'Incorrect email or password.';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-center flex-grow-1 py-4">
  <div class="card auth-card w-100 my-0"><div class="card-body p-4">
    <h3 class="mb-3">Welcome back</h3>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="needs-validation" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" required><div class="invalid-feedback">Enter your email.</div></div>
      <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" required><div class="invalid-feedback">Enter your password.</div></div>
      <button class="btn btn-rx w-100">Log in</button>
    </form>
    <p class="text-center mt-3 mb-1 small"><a href="<?= url('forgot_password.php') ?>">Forgot password?</a></p>
    <p class="text-center mb-0 small">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>
  </div></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
