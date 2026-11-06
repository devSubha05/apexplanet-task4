<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_shop_owner();
$shopArea = true;
$pageTitle = 'Shop Orders';
$pdo = db();

$shop = owner_shop($me['id']);
if (!$shop) {
    redirect('shop/index.php');
}

$validStatuses = ['pending', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $oid = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if ($action === 'status' && in_array($newStatus, $validStatuses, true)) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM order_items oi JOIN foods f ON f.id=oi.food_id WHERE oi.order_id=? AND f.shop_id=?');
        $chk->execute([$oid, $shop['id']]);
        if ($chk->fetchColumn() > 0) {
            $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$newStatus, $oid]);
            flash('success', "Order #$oid updated to " . ucwords(str_replace('_', ' ', $newStatus)) . ".");
        }
    }
    header('Location: ' . url('shop/orders.php' . (isset($_POST['back']) ? '?' . ltrim($_POST['back'], '?') : '')));
    exit;
}

$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['f.shop_id = ?'];
$args = [$shop['id']];

if (in_array($status, $validStatuses, true)) {
    $where[] = 'o.status = ?';
    $args[] = $status;
}
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.phone LIKE ? OR o.id = ? OR oi.food_name LIKE ?)';
    $args[] = "%$q%";
    $args[] = "%$q%";
    $args[] = (int)$q;
    $args[] = "%$q%";
}
$w = implode(' AND ', $where);

// Count total orders matching criteria
$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT o.id)
    FROM orders o
    JOIN users u ON u.id = o.user_id
    JOIN order_items oi ON oi.order_id = o.id
    JOIN foods f ON f.id = oi.food_id
    WHERE $w");
$countStmt->execute($args);
$total = (int)$countStmt->fetchColumn();

$perPage = PER_PAGE;
$offset = ($page - 1) * $perPage;

$ordersStmt = $pdo->prepare("SELECT DISTINCT
    o.id, o.user_id, o.status, o.payment_method, o.delivery_address,
    o.phone AS order_phone, o.note, o.created_at,
    u.name AS customer_name, u.email AS customer_email,
    SUM(oi.price * oi.quantity) AS shop_subtotal
FROM orders o
JOIN users u ON u.id = o.user_id
JOIN order_items oi ON oi.order_id = o.id
JOIN foods f ON f.id = oi.food_id
WHERE $w
GROUP BY o.id, o.user_id, o.status, o.payment_method, o.delivery_address, o.phone, o.note, o.created_at, u.name, u.email
ORDER BY o.id DESC
LIMIT $perPage OFFSET $offset");
$ordersStmt->execute($args);
$orders = $ordersStmt->fetchAll();

$orderIds = array_column($orders, 'id');
$itemsByOrder = [];
if (!empty($orderIds)) {
    $inClause = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $pdo->prepare("SELECT oi.order_id, oi.food_name, oi.price, oi.quantity, f.is_veg
        FROM order_items oi
        JOIN foods f ON f.id = oi.food_id
        WHERE oi.order_id IN ($inClause) AND f.shop_id = ?
        ORDER BY oi.id ASC");
    $itemsStmt->execute(array_merge($orderIds, [$shop['id']]));
    foreach ($itemsStmt->fetchAll() as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

$qs = http_build_query(array_filter(['status' => $status, 'q' => $q]));
$back = $qs . ($page > 1 ? ($qs ? '&' : '') . 'page=' . $page : '');
$qs = $qs ? $qs . '&' : '';

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h2 class="mb-0 fw-bold">Shop Orders</h2>
    <small class="text-muted"><?= e($shop['name']) ?> &middot; <?= $total ?> total matching order<?= $total === 1 ? '' : 's' ?></small>
  </div>
  <a href="<?= url('shop/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
</div>

<!-- Filter Bar -->
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5">
    <input class="form-control" type="search" name="q" placeholder="Customer name, phone, order # or food..." value="<?= e($q) ?>">
  </div>
  <div class="col-md-4">
    <select name="status" class="form-select" aria-label="Status">
      <option value="">All statuses</option>
      <?php foreach ($validStatuses as $v): ?>
        <option value="<?= $v ?>" <?= $status === $v ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $v))) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 d-grid">
    <button class="btn btn-outline-rx">Filter Orders</button>
  </div>
</form>

<div class="table-responsive shadow-sm border-0">
  <table class="table align-middle mb-0 table-hover">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Customer (Who Ordered)</th>
        <th>Foods Ordered</th>
        <th>Shop Total</th>
        <th>Date</th>
        <th>Status &amp; Action</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td class="fw-bold align-top">
          #<?= $o['id'] ?>
          <div class="badge bg-light text-dark border text-uppercase mt-1"><?= e($o['payment_method']) ?></div>
        </td>
        <td class="align-top">
          <div class="fw-bold"><?= e($o['customer_name']) ?></div>
          <div class="small text-muted"><i class="bi bi-telephone me-1"></i><a href="tel:<?= e($o['order_phone']) ?>"><?= e($o['order_phone']) ?></a></div>
          <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= e($o['customer_email']) ?></div>
          <div class="small text-secondary mt-1 p-1 bg-light rounded"><i class="bi bi-geo-alt me-1"></i><?= e($o['delivery_address']) ?></div>
          <?php if (!empty($o['note'])): ?><div class="small text-danger fst-italic mt-1"><i class="bi bi-chat-left-dots me-1"></i><?= e($o['note']) ?></div><?php endif; ?>
        </td>
        <td class="align-top" style="min-width:240px">
          <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
            <div class="d-flex justify-content-between small py-1 border-bottom">
              <div>
                <span class="<?= $it['is_veg'] ? 'veg-dot' : 'nonveg-dot' ?> me-1"></span>
                <strong><?= e($it['food_name']) ?></strong> &times; <?= (int)$it['quantity'] ?>
              </div>
              <span class="text-muted ms-2"><?= money($it['price'] * $it['quantity']) ?></span>
            </div>
          <?php endforeach; ?>
        </td>
        <td class="align-top fw-bold text-success fs-6"><?= money($o['shop_subtotal']) ?></td>
        <td class="align-top small text-muted"><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></td>
        <td class="align-top">
          <form method="post" class="d-flex gap-1">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <input type="hidden" name="back" value="<?= e($back) ?>">
            <select name="status" class="form-select form-select-sm" style="min-width:130px">
              <?php foreach ($validStatuses as $v): ?>
                <option value="<?= $v ?>" <?= $o['status'] === $v ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $v))) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; if (!$orders): ?>
      <tr><td colspan="6" class="text-center text-muted py-5">No orders found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="mt-3"><?= paginate($total, $page, $perPage, $qs) ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

