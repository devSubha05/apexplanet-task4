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
    } elseif ($action === 'assign_rider') {
        $riderId = !empty($_POST['delivery_boy_id']) ? (int)$_POST['delivery_boy_id'] : null;
        $chk = $pdo->prepare('SELECT COUNT(*) FROM order_items oi JOIN foods f ON f.id=oi.food_id WHERE oi.order_id=? AND f.shop_id=?');
        $chk->execute([$oid, $shop['id']]);
        if ($chk->fetchColumn() > 0) {
            $pdo->prepare("UPDATE orders SET delivery_boy_id=?, status = IF(status='pending', 'preparing', status) WHERE id=?")
                ->execute([$riderId, $oid]);
            if ($riderId) {
                $rName = $pdo->query("SELECT name FROM users WHERE id = $riderId")->fetchColumn();
                flash('success', "Assigned $rName to Order #$oid! Order moved to Preparing.");
            } else {
                flash('warning', "Delivery partner unassigned from Order #$oid.");
            }
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
    o.id, o.user_id, o.status, o.payment_method, o.delivery_address, o.delivery_boy_id,
    o.phone AS order_phone, o.note, o.created_at,
    u.name AS customer_name, u.email AS customer_email,
    d.name AS delivery_boy_name, d.phone AS delivery_boy_phone,
    SUM(oi.price * oi.quantity) AS shop_subtotal
FROM orders o
JOIN users u ON u.id = o.user_id
LEFT JOIN users d ON d.id = o.delivery_boy_id
JOIN order_items oi ON oi.order_id = o.id
JOIN foods f ON f.id = oi.food_id
WHERE $w
GROUP BY o.id, o.user_id, o.status, o.payment_method, o.delivery_address, o.delivery_boy_id, o.phone, o.note, o.created_at, u.name, u.email, d.name, d.phone
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

// Fetch live delivery partners and their active workloads
$ridersStmt = $pdo->query("SELECT u.id, u.name, u.email, u.phone, u.address, u.avatar,
    COUNT(CASE WHEN o.status IN ('preparing', 'out_for_delivery') THEN o.id END) AS active_deliveries,
    COUNT(CASE WHEN o.status = 'delivered' AND DATE(o.delivered_at) = CURDATE() THEN o.id END) AS delivered_today
FROM users u
LEFT JOIN orders o ON o.delivery_boy_id = u.id
WHERE u.role = 'delivery' AND u.status = 'active'
GROUP BY u.id, u.name, u.email, u.phone, u.address, u.avatar
ORDER BY active_deliveries ASC, u.name ASC");
$deliveryBoys = $ridersStmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h2 class="mb-0 fw-bold">Shop Orders</h2>
    <small class="text-muted"><?= e($shop['name']) ?> &middot; <?= $total ?> total matching order<?= $total === 1 ? '' : 's' ?></small>
  </div>
  <a href="<?= url('shop/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
</div>

<!-- Quick Delivery Boys Availability Widget -->
<div class="card border-0 shadow-sm mb-3" style="border-radius: 0.8rem; background: #fff8f0; border-left: 4px solid #f7b32b !important;">
  <div class="card-body py-2 px-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-bicycle fs-4 text-warning"></i>
        <div>
          <strong class="text-dark">Live Delivery Boys Fleet:</strong>
          <span class="text-muted small ms-1">Check who is free before assigning incoming orders:</span>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($deliveryBoys as $dboy):
          $isFree = ((int)$dboy['active_deliveries'] === 0);
        ?>
          <div class="badge <?= $isFree ? 'bg-success bg-opacity-10 text-success border border-success' : 'bg-warning bg-opacity-10 text-dark border border-warning' ?> p-2 d-flex align-items-center gap-2">
            <span class="rounded-circle p-1 <?= $isFree ? 'bg-success' : 'bg-warning' ?>"></span>
            <span><strong><?= e($dboy['name']) ?></strong>: <?= $isFree ? 'Free & Ready' : (int)$dboy['active_deliveries'] . ' in transit' ?></span>
            <a href="tel:<?= e($dboy['phone']) ?>" class="text-decoration-none text-muted" title="Call <?= e($dboy['phone']) ?>"><i class="bi bi-telephone-fill"></i></a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

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
        <th>Delivery Partner</th>
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
        
        <td class="align-top" style="min-width: 220px;">
          <?php if (!empty($o['delivery_boy_id'])): ?>
            <div class="p-2 rounded bg-light border">
              <div class="d-flex align-items-center gap-1 mb-1">
                <i class="bi bi-bicycle text-primary fs-5"></i>
                <div>
                  <strong class="d-block text-dark small"><?= e($o['delivery_boy_name']) ?></strong>
                  <a href="tel:<?= e($o['delivery_boy_phone']) ?>" class="small text-muted text-decoration-none"><i class="bi bi-telephone text-success"></i> <?= e($o['delivery_boy_phone']) ?></a>
                </div>
              </div>
              <form method="post" class="mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign_rider">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <div class="input-group input-group-sm">
                  <select name="delivery_boy_id" class="form-select form-select-sm" style="font-size: 0.75rem;">
                    <option value="">-- Remove / Reassign --</option>
                    <?php foreach ($deliveryBoys as $r): ?>
                      <option value="<?= $r['id'] ?>" <?= (int)$o['delivery_boy_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                        <?= e($r['name']) ?> (<?= (int)$r['active_deliveries'] === 0 ? '🟢 Free' : (int)$r['active_deliveries'] . ' active' ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-outline-secondary" title="Save Rider"><i class="bi bi-arrow-repeat"></i></button>
                </div>
              </form>
            </div>
          <?php else: ?>
            <div class="p-2 rounded bg-warning bg-opacity-10 border border-warning">
              <span class="badge bg-warning text-dark mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>No Rider</span>
              <form method="post" class="d-flex flex-column gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign_rider">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <select name="delivery_boy_id" class="form-select form-select-sm" required style="font-size: 0.75rem;">
                  <option value="">-- Select Available Rider --</option>
                  <?php foreach ($deliveryBoys as $r): ?>
                    <option value="<?= $r['id'] ?>">
                      <?= e($r['name']) ?> (<?= (int)$r['active_deliveries'] === 0 ? '🟢 Free' : '🟡 ' . (int)$r['active_deliveries'] . ' busy' ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-danger fw-semibold">
                  <i class="bi bi-send-check-fill me-1"></i> Assign Rider
                </button>
              </form>
            </div>
          <?php endif; ?>
        </td>

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
      <tr><td colspan="7" class="text-center text-muted py-5">No orders found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="mt-3"><?= paginate($total, $page, $perPage, $qs) ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

