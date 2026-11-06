<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Forgot password';
$link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $s = db()->prepare('SELECT id FROM users WHERE email=? AND status="active"'); $s->execute([$email]);
    if ($u = $s->fetch()) {
        $token = bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
            ->execute([$u['id'], hash('sha256', $token)]);
        // DEMO MODE: no mail server on localhost, so the link is shown on screen.
        // In production, email this link instead of displaying it.
        $link = url('reset_password.php?token=' . $token);
    }
    flash('info', 'If that email is registered, a reset link has been generated.');
}
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card"><div class="card-body p-4">
  <h3 class="mb-3">Reset your password</h3>
  <form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="email">Registered email</label><input class="form-control" type="email" id="email" name="email" required><div class="invalid-feedback">Enter your email.</div></div>
    <button class="btn btn-rx w-100">Get reset link</button>
  </form>
  <?php if ($link): ?>
    <div class="alert alert-warning mt-3 small mb-0"><strong>Demo mode:</strong> no email server on localhost. Use this link (valid 30 minutes):<br><a href="<?= e($link) ?>">Open reset page</a></div>
  <?php endif; ?>
  <p class="text-center mt-3 mb-0 small"><a href="<?= url('login.php') ?>">Back to log in</a></p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
