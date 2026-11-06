<?php
require_once __DIR__ . '/includes/auth.php';
$me = require_login();
$pageTitle = 'My orders';

/* Cancel (only while pending) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $oid = (int)($_POST['order_id'] ?? 0);
    $s = db()->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND user_id=? AND status='pending'");
    $s->execute([$oid, $me['id']]);
    flash($s->rowCount() ? 'success' : 'warning', $s->rowCount() ? "Order #$oid cancelled." : 'This order can no longer be cancelled.');
    redirect('orders.php?id=' . $oid);
}

/* Single order */
if (isset($_GET['id'])) {
    $oid = (int)$_GET['id'];
    $s = db()->prepare('SELECT * FROM orders WHERE id=? AND user_id=?'); $s->execute([$oid, $me['id']]);
    $o = $s->fetch();
    if (!$o) { http_response_code(404); exit('Order not found.'); }
    $it = db()->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$oid]);
    $lines = $it->fetchAll();
    require __DIR__ . '/includes/header.php';
?>
<a href="<?= url('orders.php') ?>" class="small">&larr; All orders</a>
<h2 class="mt-2">Order #<?= $o['id'] ?> <span class="badge status-<?= e($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></h2>
<p class="text-muted small">Placed <?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?> &middot; <?= $o['payment_method'] === 'cod' ? 'Cash on delivery' : 'UPI on delivery' ?></p>
<div class="row g-4">
  <div class="col-lg-8"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Dish</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
    <tbody><?php foreach ($lines as $l): ?><tr><td><?= e($l['food_name']) ?></td><td><?= money($l['price']) ?></td><td><?= $l['quantity'] ?></td><td><?= money($l['price'] * $l['quantity']) ?></td></tr><?php endforeach; ?></tbody>
    <tfoot><tr><th colspan="3" class="text-end">Total</th><th><?= money($o['total']) ?></th></tr></tfoot>
  </table></div></div>
  <div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body">
    <h6>Delivery</h6><p class="mb-1"><?= nl2br(e($o['delivery_address'])) ?></p><p class="mb-2"><i class="bi bi-telephone"></i> <?= e($o['phone']) ?></p>
    <?php if ($o['note']): ?><p class="small text-muted">Note: <?= e($o['note']) ?></p><?php endif; ?>
    <?php if ($o['status'] === 'pending'): ?>
      <form method="post" data-confirm="Cancel this order?"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $o['id'] ?>"><button class="btn btn-outline-danger btn-sm">Cancel order</button></form>
    <?php endif; ?>
  </div></div></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; exit; }

/* List with filter + pagination */
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$valid = ['pending', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];
$where = 'user_id=?'; $args = [$me['id']];
if (in_array($status, $valid, true)) { $where .= ' AND status=?'; $args[] = $status; }
$c = db()->prepare("SELECT COUNT(*) FROM orders WHERE $where"); $c->execute($args); $total = (int)$c->fetchColumn();
$off = ($page - 1) * PER_PAGE;
$s = db()->prepare("SELECT * FROM orders WHERE $where ORDER BY id DESC LIMIT " . PER_PAGE . " OFFSET $off"); $s->execute($args);
$rows = $s->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h2 class="mb-0">My orders</h2>
  <form method="get"><select name="status" class="form-select" onchange="this.form.submit()" aria-label="Filter by status">
    <option value="">All statuses</option>
    <?php foreach ($valid as $v): ?><option value="<?= $v ?>" <?= $status === $v ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $v))) ?></option><?php endforeach; ?>
  </select></form>
</div>
<?php if (!$rows): ?><p class="text-muted">No orders yet. <a href="<?= url('index.php') ?>">Order something tasty</a>.</p>
<?php else: ?>
<div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>#</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead>
  <tbody><?php foreach ($rows as $o): ?>
    <tr><td><?= $o['id'] ?></td><td><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></td><td><?= money($o['total']) ?></td>
    <td><span class="badge status-<?= e($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
    <td><a class="btn btn-sm btn-outline-rx" href="?id=<?= $o['id'] ?>">View</a></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<div class="mt-3"><?= paginate($total, $page, PER_PAGE, $status ? 'status=' . urlencode($status) . '&' : '') ?></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
