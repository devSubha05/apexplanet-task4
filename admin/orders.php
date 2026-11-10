<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$adminArea = true; $pageTitle = 'Manage orders';
$valid = ['pending', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $action = $_POST['action'] ?? '';
    if ($action === 'status' && in_array($_POST['status'] ?? '', $valid, true)) {
        db()->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$_POST['status'], $id]);
        flash('success', "Order #$id updated.");
    } elseif ($action === 'assign') {
        $boyId = !empty($_POST['delivery_boy_id']) ? (int)$_POST['delivery_boy_id'] : null;
        db()->prepare('UPDATE orders SET delivery_boy_id=? WHERE id=?')->execute([$boyId, $id]);
        flash('success', "Delivery partner updated for order #$id.");
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM orders WHERE id=?')->execute([$id]);
        flash('success', "Order #$id deleted.");
    }
    header('Location: ' . url('admin/orders.php' . (isset($_POST['back']) ? '?' . ltrim($_POST['back'], '?') : ''))); exit;
}

$status = $_GET['status'] ?? ''; $q = trim($_GET['q'] ?? ''); $page = max(1, (int)($_GET['page'] ?? 1));
$where = ['1=1']; $args = [];
if (in_array($status, $valid, true)) { $where[] = 'o.status=?'; $args[] = $status; }
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR o.id = ?)'; $args[] = "%$q%"; $args[] = "%$q%"; $args[] = (int)$q; }
$w = implode(' AND ', $where);
$c = db()->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id=o.user_id WHERE $w"); $c->execute($args); $total = (int)$c->fetchColumn();
$off = ($page - 1) * PER_PAGE;
$deliveryBoys = db()->query("SELECT id, name, phone FROM users WHERE role='delivery' AND status='active' ORDER BY name ASC")->fetchAll();

$s = db()->prepare("SELECT o.*, u.name, u.email, d.name AS delivery_boy_name, (SELECT GROUP_CONCAT(CONCAT(oi.food_name,' x',oi.quantity) SEPARATOR ', ') FROM order_items oi WHERE oi.order_id=o.id) items
  FROM orders o
  JOIN users u ON u.id=o.user_id
  LEFT JOIN users d ON d.id=o.delivery_boy_id
  WHERE $w ORDER BY o.id DESC LIMIT " . PER_PAGE . " OFFSET $off");
$s->execute($args); $rows = $s->fetchAll();
$qs = http_build_query(array_filter(['status' => $status, 'q' => $q])); $back = $qs . ($page > 1 ? ($qs ? '&' : '') . 'page=' . $page : ''); $qs = $qs ? $qs . '&' : '';
require __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-3">Orders</h2>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input class="form-control" type="search" name="q" placeholder="Order #, customer name or email" value="<?= e($q) ?>"></div>
  <div class="col-md-4"><select name="status" class="form-select" aria-label="Status"><option value="">All statuses</option><?php foreach ($valid as $v): ?><option value="<?= $v ?>" <?= $status === $v ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $v))) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3 d-grid"><button class="btn btn-outline-rx">Filter</button></div>
</form>
<div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>#</th><th>Customer</th><th>Items</th><th>Total</th><th>Date</th><th>Delivery Partner</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $o): ?>
    <tr>
      <td><?= $o['id'] ?></td>
      <td><?= e($o['name']) ?><div class="small text-muted"><?= e($o['phone']) ?></div></td>
      <td class="small" style="max-width:260px"><?= e($o['items']) ?></td>
      <td><?= money($o['total']) ?></td>
      <td class="small"><?= e(date('d M, h:i A', strtotime($o['created_at']))) ?></td>
      <td>
        <form method="post" class="d-flex gap-1">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="assign">
          <input type="hidden" name="id" value="<?= $o['id'] ?>">
          <input type="hidden" name="back" value="<?= e($back) ?>">
          <select name="delivery_boy_id" class="form-select form-select-sm" style="min-width: 140px;" aria-label="Assign Delivery Boy">
            <option value="">-- Unassigned --</option>
            <?php foreach ($deliveryBoys as $dboy): ?>
              <option value="<?= $dboy['id'] ?>" <?= (int)$o['delivery_boy_id'] === (int)$dboy['id'] ? 'selected' : '' ?>>
                <?= e($dboy['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-outline-primary" title="Save Partner"><i class="bi bi-check-lg"></i></button>
        </form>
      </td>
      <td><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
        <select name="status" class="form-select form-select-sm" aria-label="Order status"><?php foreach ($valid as $v): ?><option value="<?= $v ?>" <?= $o['status'] === $v ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $v))) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-outline-secondary">Save</button></form></td>
      <td><form method="post" data-confirm="Delete order #<?= $o['id'] ?> permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No orders found.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<div class="mt-3"><?= paginate($total, $page, PER_PAGE, $qs) ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
