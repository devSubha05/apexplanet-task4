<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('dashboard.php');
$pageTitle = 'Sign up';
$errors = []; $old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $pw    = $_POST['password'] ?? '';
    $cf    = $_POST['confirm'] ?? '';
    $old = compact('name', 'email', 'phone');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $errors[] = 'Enter your name (2-80 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!valid_phone($phone)) $errors[] = 'Phone must be 10 digits.';
    if ($e = valid_password($pw)) $errors[] = $e;
    if ($pw !== $cf) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $s = db()->prepare('SELECT id FROM users WHERE email=?'); $s->execute([$email]);
        if ($s->fetch()) $errors[] = 'An account with this email already exists.';
    }
    if (!$errors) {
        db()->prepare('INSERT INTO users (name,email,phone,password_hash) VALUES (?,?,?,?)')
            ->execute([$name, $email, $phone, password_hash($pw, PASSWORD_DEFAULT)]);
        flash('success', 'Account created. Please log in.');
        redirect('login.php');
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card"><div class="card-body p-4">
  <h3 class="mb-3">Create your account</h3>
  <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" required minlength="2" maxlength="80" value="<?= e($old['name']) ?>"><div class="invalid-feedback">Enter your name.</div></div>
    <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" required value="<?= e($old['email']) ?>"><div class="invalid-feedback">Enter a valid email.</div></div>
    <div class="mb-3"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" required pattern="[0-9]{10}" inputmode="numeric" value="<?= e($old['phone']) ?>"><div class="invalid-feedback">10 digits, numbers only.</div></div>
    <div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" data-strong required><div class="invalid-feedback">Min 8 chars with upper, lower case and a number.</div></div>
    <div class="mb-3"><label class="form-label" for="confirm">Confirm password</label><input class="form-control" type="password" id="confirm" name="confirm" required><div class="invalid-feedback">Passwords must match.</div></div>
    <button class="btn btn-rx w-100">Create account</button>
  </form>
  <p class="text-center mt-3 mb-0 small">Already have an account? <a href="<?= url('login.php') ?>">Log in</a></p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
