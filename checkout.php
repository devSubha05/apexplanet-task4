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
$address = $me['address'] ?? '';
$pincode = '';
// Auto-detect 6-digit pincode from user's saved profile address if present
if (preg_match('/\b([1-9][0-9]{5})\b/', $address, $pinMatch)) {
    $pincode = $pinMatch[1];
}
$phone = $me['phone'] ?? '';
$note = '';
$pay = 'cod';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $address = trim($_POST['address'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $note    = trim($_POST['note'] ?? '');
    $pay     = $_POST['payment'] ?? 'cod';

    if (mb_strlen($address) < 8 || mb_strlen($address) > 255) {
        $errors[] = 'Enter a full delivery address (8-255 characters).';
    }
    if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
        $errors[] = 'Enter a valid 6-digit delivery PIN code (e.g. 713201).';
    }
    if (!valid_phone($phone)) {
        $errors[] = 'Phone must be a valid 10-digit number.';
    }
    if (!in_array($pay, ['cod', 'upi'], true)) {
        $errors[] = 'Choose a payment method.';
    }
    if (mb_strlen($note) > 255) {
        $errors[] = 'Note is too long.';
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            // Append PIN code to delivery address if not already explicitly written there
            $fullDeliveryAddress = $address;
            if (!str_contains($address, $pincode)) {
                $fullDeliveryAddress .= " - PIN: $pincode";
            }

            // Insert order with dedicated pincode column and full address
            $pdo->prepare('INSERT INTO orders (user_id,total,payment_method,delivery_address,pincode,phone,note) VALUES (?,?,?,?,?,?,?)')
                ->execute([$me['id'], $total, $pay, $fullDeliveryAddress, $pincode, $phone, $note ?: null]);
            $oid = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare('INSERT INTO order_items (order_id,food_id,food_name,price,quantity) VALUES (?,?,?,?,?)');
            foreach ($items as $f) {
                $ins->execute([$oid, $f['id'], $f['name'], $f['price'], $f['qty']]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            flash('success', "Order #$oid placed successfully! We'll deliver to PIN $pincode.");
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
<h2 class="mb-3"><i class="bi bi-bag-check me-2 text-danger"></i>Checkout</h2>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>

<div class="row g-4">
  <div class="col-lg-7">
    <form method="post" class="needs-validation card card-body border-0 shadow-sm" novalidate>
      <?= csrf_field() ?>

      <!-- Delivery Address -->
      <div class="mb-3">
        <label class="form-label fw-semibold" for="address">
          <i class="bi bi-house-door me-1 text-danger"></i> Delivery Address
        </label>
        <textarea class="form-control" id="address" name="address" rows="3" required minlength="8" maxlength="255" placeholder="Flat / House No., Street, Area, Landmark, City"><?= e($address) ?></textarea>
        <div class="invalid-feedback">Enter your street / house delivery address.</div>
      </div>

      <!-- Pincode and Phone Row -->
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="pincode">
            <i class="bi bi-geo-alt me-1 text-danger"></i> Delivery Pincode
          </label>
          <div class="input-group has-validation">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-pin-map"></i></span>
            <input class="form-control" id="pincode" name="pincode" required pattern="^[1-9][0-9]{5}$" maxlength="6" inputmode="numeric" placeholder="e.g. 713201" value="<?= e($pincode) ?>">
            <div class="invalid-feedback">Enter a valid 6-digit PIN code.</div>
          </div>
          <small class="form-text text-muted">6-digit postal code of your delivery area</small>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold" for="phone">
            <i class="bi bi-telephone me-1 text-danger"></i> Contact Phone
          </label>
          <div class="input-group has-validation">
            <span class="input-group-text bg-light text-muted">+91</span>
            <input class="form-control" id="phone" name="phone" required pattern="[0-9]{10}" maxlength="10" inputmode="numeric" placeholder="10-digit mobile number" value="<?= e($phone) ?>">
            <div class="invalid-feedback">Enter a 10-digit mobile number.</div>
          </div>
          <small class="form-text text-muted">Delivery partner will call this number</small>
        </div>
      </div>

      <!-- Note for the kitchen -->
      <div class="mb-3">
        <label class="form-label fw-semibold" for="note">
          <i class="bi bi-chat-left-text me-1 text-danger"></i> Note for the kitchen (optional)
        </label>
        <input class="form-control" id="note" name="note" maxlength="255" placeholder="e.g. Less spicy, call on arrival, extra napkins" value="<?= e($note) ?>">
      </div>

      <!-- Payment Method -->
      <div class="mb-4">
        <label class="form-label fw-semibold d-block">
          <i class="bi bi-credit-card me-1 text-danger"></i> Payment Method
        </label>
        <div class="card bg-light border p-3">
          <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="payment" id="cod" value="cod" <?= $pay === 'cod' ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="cod">
              <i class="bi bi-cash-stack me-1 text-success"></i> Cash on Delivery (COD)
            </label>
            <div class="text-muted small ps-4">Pay with cash when your food arrives.</div>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="payment" id="upi" value="upi" <?= $pay === 'upi' ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="upi">
              <i class="bi bi-qr-code-scan me-1 text-primary"></i> UPI on Delivery
            </label>
            <div class="text-muted small ps-4">Scan QR code / GPay / PhonePe / Paytm upon delivery.</div>
          </div>
        </div>
      </div>

      <button class="btn btn-rx btn-lg w-100 shadow-sm">
        <i class="bi bi-check2-circle me-1"></i> Place Order &middot; <?= money($total) ?>
      </button>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm sticky-top" style="top: 1rem;">
      <div class="card-body">
        <h5 class="card-title fw-bold border-bottom pb-2 mb-3">Order Summary</h5>
        <ul class="list-group list-group-flush mb-3">
          <?php foreach ($items as $f): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <div>
                <span class="<?= $f['is_veg'] ? 'veg-dot' : 'nonveg-dot' ?> me-1"></span>
                <strong><?= e($f['name']) ?></strong>
                <div class="small text-muted"><?= money($f['price']) ?> &times; <?= $f['qty'] ?></div>
              </div>
              <span class="fw-semibold"><?= money($f['qty'] * $f['price']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="d-flex justify-content-between align-items-center fs-5 fw-bold pt-2 border-top">
          <span>Grand Total</span>
          <span class="text-danger"><?= money($total) ?></span>
        </div>
        <div class="small text-muted mt-2 text-center">
          <i class="bi bi-shield-check text-success me-1"></i> Free contactless delivery included
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
