<?php
require_once __DIR__ . '/includes/auth.php';
if ($u = current_user()) {
    if ($u['role'] === 'shop_owner') redirect('shop/index.php');
    if ($u['role'] === 'delivery') redirect('delivery/index.php');
    if ($u['role'] === 'admin') redirect('admin/index.php');
    redirect('index.php');
}
$pageTitle = 'Sign up';
$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $role  = trim($_POST['role'] ?? 'user');
    $pw    = $_POST['password'] ?? '';
    $cf    = $_POST['confirm'] ?? '';

    $allowedRoles = ['user', 'shop_owner', 'delivery', 'admin'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'user';
    }

    $old = compact('name', 'email', 'phone', 'role');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $errors[] = 'Enter your name (2-80 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (!valid_phone($phone)) $errors[] = 'Phone must be 10 digits.';
    if ($e = valid_password($pw)) $errors[] = $e;
    if ($pw !== $cf) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $s = db()->prepare('SELECT id FROM users WHERE email=?');
        $s->execute([$email]);
        if ($s->fetch()) $errors[] = 'An account with this email already exists.';
    }

    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,?)');
        $stmt->execute([$name, $email, $phone, password_hash($pw, PASSWORD_DEFAULT), $role]);
        $newUserId = (int)db()->lastInsertId();

        if ($role === 'shop_owner') {
            $shopName = $name . "'s Kitchen";
            db()->prepare('INSERT INTO shops (name, description, address, phone, owner_id) VALUES (?, ?, ?, ?, ?)')
                ->execute([$shopName, 'Freshly prepared home-style food and dishes.', 'Main Street, City Center', $phone ?: '9000000000', $newUserId]);
        }

        $roleLabels = [
            'user' => 'Customer',
            'delivery' => 'Delivery Partner',
            'shop_owner' => 'Shop Owner',
            'admin' => 'Administrator'
        ];
        $roleText = $roleLabels[$role] ?? 'Customer';
        flash('success', "Account registered as $roleText! Please log in.");
        redirect('login.php');
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-center flex-grow-1 py-4">
  <div class="card auth-card w-100 my-0 shadow-sm"><div class="card-body p-4">
    <div class="text-center mb-4">
      <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 text-danger rounded-circle p-3 mb-2">
        <i class="bi bi-person-plus-fill fs-2"></i>
      </div>
      <h3 class="fw-bold mb-1">Create Account</h3>
      <p class="text-muted small mb-0">Join Rasoi Express and select your role to get started</p>
    </div>

    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>

    <form method="post" class="needs-validation" novalidate>
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label fw-bold mb-2">
          <i class="bi bi-person-badge me-1 text-danger"></i>Select Your Role
        </label>
        <div class="row g-2">
          <div class="col-12 col-sm-6">
            <input type="radio" class="btn-check" name="role" id="role_user" value="user" <?= ($old['role'] ?? 'user') === 'user' ? 'checked' : '' ?> required>
            <label class="btn w-100 text-start p-2 p-sm-3 role-card" for="role_user">
              <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                  <i class="bi bi-person-fill fs-5"></i>
                </div>
                <div>
                  <div class="fw-bold role-title text-dark">Customer</div>
                  <div class="text-muted small" style="font-size: 0.75rem;">Order food online</div>
                </div>
              </div>
            </label>
          </div>

          <div class="col-12 col-sm-6">
            <input type="radio" class="btn-check" name="role" id="role_delivery" value="delivery" <?= ($old['role'] ?? '') === 'delivery' ? 'checked' : '' ?>>
            <label class="btn w-100 text-start p-2 p-sm-3 role-card" for="role_delivery">
              <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                  <i class="bi bi-bicycle fs-5"></i>
                </div>
                <div>
                  <div class="fw-bold role-title text-dark">Delivery Boy</div>
                  <div class="text-muted small" style="font-size: 0.75rem;">Deliver food orders</div>
                </div>
              </div>
            </label>
          </div>

          <div class="col-12 col-sm-6">
            <input type="radio" class="btn-check" name="role" id="role_shop" value="shop_owner" <?= ($old['role'] ?? '') === 'shop_owner' ? 'checked' : '' ?>>
            <label class="btn w-100 text-start p-2 p-sm-3 role-card" for="role_shop">
              <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-success bg-opacity-10 text-success p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                  <i class="bi bi-shop fs-5"></i>
                </div>
                <div>
                  <div class="fw-bold role-title text-dark">Shop Owner</div>
                  <div class="text-muted small" style="font-size: 0.75rem;">Manage restaurant</div>
                </div>
              </div>
            </label>
          </div>

          <div class="col-12 col-sm-6">
            <input type="radio" class="btn-check" name="role" id="role_admin" value="admin" <?= ($old['role'] ?? '') === 'admin' ? 'checked' : '' ?>>
            <label class="btn w-100 text-start p-2 p-sm-3 role-card" for="role_admin">
              <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                  <i class="bi bi-shield-lock-fill fs-5"></i>
                </div>
                <div>
                  <div class="fw-bold role-title text-dark">Admin</div>
                  <div class="text-muted small" style="font-size: 0.75rem;">Platform manager</div>
                </div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="name">Full name</label>
        <input class="form-control" id="name" name="name" required minlength="2" maxlength="80" value="<?= e($old['name']) ?>" placeholder="e.g. John Doe">
        <div class="invalid-feedback">Enter your name.</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" required value="<?= e($old['email']) ?>" placeholder="name@example.com">
        <div class="invalid-feedback">Enter a valid email.</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="phone">Phone</label>
        <input class="form-control" id="phone" name="phone" required pattern="[0-9]{10}" inputmode="numeric" value="<?= e($old['phone']) ?>" placeholder="10-digit mobile number">
        <div class="invalid-feedback">10 digits, numbers only.</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" data-strong required placeholder="Create a strong password">
        <div class="invalid-feedback">Min 8 chars with upper, lower case and a number.</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="confirm">Confirm password</label>
        <input class="form-control" type="password" id="confirm" name="confirm" required placeholder="Repeat password">
        <div class="invalid-feedback">Passwords must match.</div>
      </div>
      <button class="btn btn-rx w-100 py-2 fw-semibold"><i class="bi bi-check-circle me-1"></i>Create account</button>
    </form>
    <p class="text-center mt-3 mb-0 small">Already have an account? <a href="<?= url('login.php') ?>" class="fw-bold">Log in</a></p>
  </div></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
