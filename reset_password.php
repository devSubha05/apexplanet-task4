<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Choose a new password';
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$hash = hash('sha256', $token);
$s = db()->prepare('SELECT * FROM password_resets WHERE token_hash=? AND used=0 AND expires_at > NOW()');
$s->execute([$hash]);
$reset = $s->fetch();
$error = '';

if (!$reset) {
    flash('danger', 'This reset link is invalid or has expired.');
    redirect('forgot_password.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pw = $_POST['password'] ?? ''; $cf = $_POST['confirm'] ?? '';
    if ($e = valid_password($pw)) $error = $e;
    elseif ($pw !== $cf) $error = 'Passwords do not match.';
    else {
        $pdo = db(); $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $reset['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used=1 WHERE user_id=?')->execute([$reset['user_id']]);
        $pdo->commit();
        flash('success', 'Password updated. Please log in.');
        redirect('login.php');
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-center flex-grow-1 py-4">
  <div class="card auth-card w-100 my-0"><div class="card-body p-4">
    <h3 class="mb-3">Choose a new password</h3>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="needs-validation" novalidate>
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control" type="password" id="password" name="password" data-strong required><div class="invalid-feedback">Min 8 chars with upper, lower case and a number.</div></div>
      <div class="mb-3"><label class="form-label" for="confirm">Confirm password</label><input class="form-control" type="password" id="confirm" name="confirm" required><div class="invalid-feedback">Passwords must match.</div></div>
      <button class="btn btn-rx w-100">Update password</button>
    </form>
  </div></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
