<?php
require_once __DIR__ . '/includes/auth.php';
$me = require_login();
$pageTitle = 'My profile';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $address = trim($_POST['address'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $errors[] = 'Enter your name (2-80 characters).';
        if (!valid_phone($phone)) $errors[] = 'Phone must be 10 digits.';
        if (mb_strlen($address) > 255) $errors[] = 'Address is too long.';
        [$img, $imgErr] = save_image($_FILES['avatar'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'avatars');
        if ($imgErr) $errors[] = $imgErr;
        if (!$errors) {
            if ($img) {
                delete_image($me['avatar'], 'avatars');
                db()->prepare('UPDATE users SET name=?,phone=?,address=?,avatar=? WHERE id=?')->execute([$name, $phone, $address ?: null, $img, $me['id']]);
            } else {
                db()->prepare('UPDATE users SET name=?,phone=?,address=? WHERE id=?')->execute([$name, $phone, $address ?: null, $me['id']]);
            }
            flash('success', 'Profile updated.'); redirect('profile.php');
        }
    }
    if ($action === 'password') {
        $cur = $_POST['current'] ?? ''; $pw = $_POST['password'] ?? ''; $cf = $_POST['confirm'] ?? '';
        $row = db()->prepare('SELECT password_hash FROM users WHERE id=?'); $row->execute([$me['id']]); $hash = $row->fetchColumn();
        if (!password_verify($cur, $hash)) $errors[] = 'Current password is incorrect.';
        elseif ($e = valid_password($pw)) $errors[] = $e;
        elseif ($pw !== $cf) $errors[] = 'New passwords do not match.';
        else {
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.'); redirect('profile.php');
        }
    }
    $me = array_merge($me, ['name' => $_POST['name'] ?? $me['name']]);
}
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-3">My profile</h2>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-4">
  <div class="col-lg-7">
    <form method="post" enctype="multipart/form-data" class="card card-body border-0 shadow-sm needs-validation" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div class="text-center mb-3">
        <img id="avatarPreview" src="<?= e(avatar_url($me)) ?>" class="avatar-lg" alt="Profile picture">
        <div class="mt-2"><input class="form-control form-control-sm mx-auto" style="max-width:280px" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" data-preview="#avatarPreview"><div class="form-text">JPG, PNG or WEBP, up to 2 MB.</div></div>
      </div>
      <div class="mb-3"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" required minlength="2" maxlength="80" value="<?= e($me['name']) ?>"></div>
      <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= e($me['email']) ?>" disabled></div>
      <div class="mb-3"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" required pattern="[0-9]{10}" value="<?= e($me['phone']) ?>"><div class="invalid-feedback">10 digits.</div></div>
      <div class="mb-3"><label class="form-label" for="address">Default delivery address</label><textarea class="form-control" id="address" name="address" rows="2" maxlength="255"><?= e($me['address']) ?></textarea></div>
      <button class="btn btn-rx">Save profile</button>
    </form>
  </div>
  <div class="col-lg-5">
    <form method="post" class="card card-body border-0 shadow-sm needs-validation" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h5>Change password</h5>
      <div class="mb-3"><label class="form-label" for="current">Current password</label><input class="form-control" type="password" id="current" name="current" required></div>
      <div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control" type="password" id="password" name="password" data-strong required><div class="invalid-feedback">Min 8 chars with upper, lower case and a number.</div></div>
      <div class="mb-3"><label class="form-label" for="confirm">Confirm new password</label><input class="form-control" type="password" id="confirm" name="confirm" required><div class="invalid-feedback">Passwords must match.</div></div>
      <button class="btn btn-outline-rx">Update password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
