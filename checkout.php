<?php
require_once __DIR__ . '/includes/auth.php';
$me = require_login();
$pageTitle = 'Checkout';
if (!cart()) { flash('warning', 'Your cart is empty.'); redirect('index.php'); }

$ids = array_keys(cart());
$in = implode(',', array_fill(0, count($ids), '?'));
$s = db()->prepare("SELECT * FROM foods WHERE id IN ($in) AND is_available=1");
$s->execute($ids);
$items = []; $total = 0;
foreach ($s->fetchAll() as $f) { $f['qty'] = cart()[$f['id']]; $total += $f['qty'] * $f['price']; $items[] = $f; }
if (!$items) { $_SESSION['cart'] = []; redirect('index.php'); }

$errors = [];
$address = $me['address'] ?? ''; $phone = $me['phone'] ?? ''; $note = ''; $pay = 'cod';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $address = trim($_POST['address'] ?? ''); $phone = trim($_POST['phone'] ?? '');
    $note = trim($_POST['note'] ?? ''); $pay = $_POST['payment'] ?? 'cod';
    if (mb_strlen($address) < 8 || mb_strlen($address) > 255) $errors[] = 'Enter a full delivery address (8-255 characters).';
    if (!valid_phone($phone)) $errors[] = 'Phone must be 10 digits.';
    if (!in_array($pay, ['cod', 'upi'], true)) $errors[] = 'Choose a payment method.';
    if (mb_strlen($note) > 255) $errors[] = 'Note is too long.';

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            // prices are re-read from the DB above, never trusted from the browser
            $pdo->prepare('INSERT INTO orders (user_id,total,payment_method,delivery_address,phone,note) VALUES (?,?,?,?,?,?)')
                ->execute([$me['id'], $total, $pay, $address, $phone, $note ?: null]);
            $oid = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO order_items (order_id,food_id,food_name,price,quantity) VALUES (?,?,?,?,?)');
            foreach ($items as $f) $ins->execute([$oid, $f['id'], $f['name'], $f['price'], $f['qty']]);
            $pdo->commit();
            $_SESSION['cart'] = [];
            flash('success', "Order #$oid placed. We'll start preparing it right away.");
            redirect('orders.php?id=' . $oid);
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($ex->getMessage());
            $errors[] = 'Could not place the order. Please try again.';
        }
    }
}
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-3">Checkout</h2>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-4">
  <div class="col-lg-7">
    <form method="post" class="needs-validation card card-body border-0 shadow-sm" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="address">Delivery address</label><textarea class="form-control" id="address" name="address" rows="3" required minlength="8" maxlength="255"><?= e($address) ?></textarea><div class="invalid-feedback">Enter a full address.</div></div>
      <div class="mb-3"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" required pattern="[0-9]{10}" value="<?= e($phone) ?>"><div class="invalid-feedback">10 digits.</div></div>
      <div class="mb-3"><label class="form-label" for="note">Note for the kitchen (optional)</label><input class="form-control" id="note" name="note" maxlength="255" value="<?= e($note) ?>"></div>
      <div class="mb-3">
        <div class="form-label">Payment</div>
        <div class="form-check"><input class="form-check-input" type="radio" name="payment" id="cod" value="cod" <?= $pay === 'cod' ? 'checked' : '' ?>><label class="form-check-label" for="cod">Cash on delivery</label></div>
        <div class="form-check"><input class="form-check-input" type="radio" name="payment" id="upi" value="upi" <?= $pay === 'upi' ? 'checked' : '' ?>><label class="form-check-label" for="upi">UPI on delivery</label></div>
      </div>
      <button class="btn btn-rx">Place order &middot; <?= money($total) ?></button>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <h5>Order summary</h5>
      <ul class="list-group list-group-flush">
        <?php foreach ($items as $f): ?><li class="list-group-item d-flex justify-content-between px-0"><span><?= e($f['name']) ?> &times; <?= $f['qty'] ?></span><span><?= money($f['qty'] * $f['price']) ?></span></li><?php endforeach; ?>
        <li class="list-group-item d-flex justify-content-between px-0 fw-bold"><span>Total</span><span><?= money($total) ?></span></li>
      </ul>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
