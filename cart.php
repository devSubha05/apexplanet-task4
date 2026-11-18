<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Your cart';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['food_id'] ?? 0);
    $cart = $_SESSION['cart'] ?? [];

    if ($action === 'add' && $id > 0) {
        $msg = '';
        $s = db()->prepare('SELECT id FROM foods WHERE id=? AND is_available=1'); $s->execute([$id]);
        if ($s->fetch()) {
            $cart[$id] = min(20, ($cart[$id] ?? 0) + 1);
            $msg = 'Added to cart.';
            flash('success', $msg);
        } else {
            $msg = 'That dish is not available.';
            flash('danger', $msg);
        }
        $_SESSION['cart'] = $cart;

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $msg,
                'cart_count' => cart_count()
            ]);
            exit;
        }

        $returnUrl = trim($_POST['return_url'] ?? '');
        if (!empty($returnUrl)) {
            header('Location: ' . $returnUrl);
            exit;
        }

        $back = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($back)) {
            header('Location: ' . $back);
            exit;
        }
        redirect('index.php');
    }
    if ($action === 'update' && $id > 0) {
        $qty = (int)($_POST['qty'] ?? 1);
        if ($qty <= 0) unset($cart[$id]); else $cart[$id] = min(20, $qty);
    }
    if ($action === 'remove') unset($cart[$id]);
    if ($action === 'clear') $cart = [];
    $_SESSION['cart'] = $cart;
    redirect('cart.php');
}

$items = []; $total = 0;
if (cart()) {
    $ids = array_keys(cart());
    $in = implode(',', array_fill(0, count($ids), '?'));
    $s = db()->prepare("SELECT * FROM foods WHERE id IN ($in) AND is_available=1");
    $s->execute($ids);
    foreach ($s->fetchAll() as $f) {
        $f['qty'] = cart()[$f['id']]; $f['line'] = $f['qty'] * $f['price'];
        $total += $f['line']; $items[] = $f;
    }
}
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-3">Your cart</h2>
<?php if (!$items): ?>
  <div class="text-center py-5"><i class="bi bi-bag fs-1 text-muted"></i><p class="text-muted mt-2">Your cart is empty.</p><a class="btn btn-rx" href="<?= url('index.php') ?>">Browse the menu</a></div>
<?php else: ?>
<div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Dish</th><th>Price</th><th style="width:150px">Qty</th><th>Total</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $f): ?>
    <tr>
      <td><img class="thumb me-2" src="<?= e(food_img($f['image'])) ?>" alt=""><?= e($f['name']) ?></td>
      <td><?= money($f['price']) ?></td>
      <td>
        <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="food_id" value="<?= $f['id'] ?>">
          <input class="form-control form-control-sm" type="number" name="qty" min="0" max="20" value="<?= $f['qty'] ?>" aria-label="Quantity">
          <button class="btn btn-sm btn-outline-secondary" title="Update"><i class="bi bi-arrow-repeat"></i></button>
        </form>
      </td>
      <td><?= money($f['line']) ?></td>
      <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="food_id" value="<?= $f['id'] ?>"><button class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-trash"></i></button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="3" class="text-end">Subtotal</th><th colspan="2"><?= money($total) ?></th></tr></tfoot>
</table></div>
<div class="d-flex justify-content-between mt-3">
  <form method="post" data-confirm="Empty your cart?"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn btn-outline-secondary">Clear cart</button></form>
  <a class="btn btn-rx" href="<?= url('checkout.php') ?>">Checkout</a>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
