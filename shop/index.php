<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_shop_owner();
$shopArea = true;
$pageTitle = 'Shop Dashboard';
$pdo = db();
$shop = owner_shop($me['id']);
if (!$shop) {
    $pdo->prepare('INSERT INTO shops (name, description, address, phone, owner_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([$me['name'] . "'s Kitchen", 'Freshly prepared meals and delicacies.', $me['address'] ?: 'Main Market, Durgapur', $me['phone'] ?: '9000000003', $me['id']]);
    $shop = owner_shop($me['id']);
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
            flash('success', "Order #$oid status updated to " . ucwords(str_replace('_', ' ', $newStatus)) . ".");
        } else {
            flash('danger', 'You do not have permission to modify this order.');
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
        } else {
            flash('danger', 'You do not have permission to modify this order.');
        }
    }
    redirect('shop/index.php');
}

$stats = $pdo->prepare("SELECT
    COUNT(DISTINCT o.id) AS total_orders,
    COUNT(DISTINCT CASE WHEN o.status='pending' THEN o.id END) AS pending_orders,
    COUNT(DISTINCT CASE WHEN o.status IN ('preparing','out_for_delivery') THEN o.id END) AS in_progress_orders,
    COUNT(DISTINCT CASE WHEN o.status='delivered' THEN o.id END) AS delivered_orders,
    COALESCE(SUM(CASE WHEN o.status<>'cancelled' THEN oi.price * oi.quantity END), 0) AS shop_revenue
FROM order_items oi
JOIN foods f ON f.id = oi.food_id
JOIN orders o ON o.id = oi.order_id
WHERE f.shop_id = ?");
$stats->execute([$shop['id']]);
$stat = $stats->fetch();

$dishCountStmt = $pdo->prepare('SELECT COUNT(*) FROM foods WHERE shop_id = ?');
$dishCountStmt->execute([$shop['id']]);
$totalDishes = (int)$dishCountStmt->fetchColumn();

$statusFilter = $_GET['status'] ?? '';
$searchFilter = trim($_GET['q'] ?? '');

$where = ['f.shop_id = ?'];
$args = [$shop['id']];

if (in_array($statusFilter, $validStatuses, true)) {
    $where[] = 'o.status = ?';
    $args[] = $statusFilter;
}
if ($searchFilter !== '') {
    $where[] = '(u.name LIKE ? OR u.phone LIKE ? OR o.id = ? OR oi.food_name LIKE ?)';
    $args[] = "%$searchFilter%";
    $args[] = "%$searchFilter%";
    $args[] = (int)$searchFilter;
    $args[] = "%$searchFilter%";
}

$whereClause = implode(' AND ', $where);

$ordersQuery = $pdo->prepare("SELECT DISTINCT
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
WHERE $whereClause
GROUP BY o.id, o.user_id, o.status, o.payment_method, o.delivery_address, o.delivery_boy_id, o.phone, o.note, o.created_at, u.name, u.email, d.name, d.phone
ORDER BY o.id DESC
LIMIT 20");
$ordersQuery->execute($args);
$orders = $ordersQuery->fetchAll();

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

$unassignedCount = 0;
foreach ($orders as $o) {
    if (empty($o['delivery_boy_id']) && in_array($o['status'], ['pending', 'preparing'], true)) {
        $unassignedCount++;
    }
}

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

$topStmt = $pdo->prepare("SELECT oi.food_name, SUM(oi.quantity) AS sold_qty, SUM(oi.price * oi.quantity) AS dish_revenue
    FROM order_items oi
    JOIN foods f ON f.id = oi.food_id
    JOIN orders o ON o.id = oi.order_id
    WHERE f.shop_id = ? AND o.status <> 'cancelled'
    GROUP BY oi.food_name
    ORDER BY sold_qty DESC
    LIMIT 5");
$topStmt->execute([$shop['id']]);
$topDishes = $topStmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #2b1b17 0%, #731b1b 100%); color: #fff; border-radius: 1rem;">
  <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <div class="badge bg-warning text-dark mb-2 px-3 py-1"><i class="bi bi-shop me-1"></i> Shop Owner Portal</div>
      <h2 class="h3 fw-bold mb-1"><?= e($shop['name']) ?></h2>
      <p class="mb-1 text-light opacity-90 small"><?= e($shop['description']) ?></p>
      <div class="d-flex flex-wrap gap-3 small text-white-50 mt-2">
        <span><i class="bi bi-geo-alt text-warning me-1"></i><?= e($shop['address']) ?></span>
        <span><i class="bi bi-telephone text-warning me-1"></i><?= e($shop['phone']) ?></span>
        <span><i class="bi bi-person text-warning me-1"></i>Owner: <?= e($me['name']) ?> (<?= e($me['email']) ?>)</span>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= url('shop/foods.php') ?>" class="btn btn-outline-light"><i class="bi bi-list-ul me-1"></i> Manage Dishes (<?= $totalDishes ?>)</a>
      <a href="<?= url('shop/food_form.php') ?>" class="btn btn-warning fw-semibold"><i class="bi bi-plus-circle me-1"></i> Add New Dish</a>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Total Orders</div>
        <div class="num text-danger"><?= (int)$stat['total_orders'] ?></div>
        <div class="small text-muted">Shop lifetime</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card shadow-sm h-100" style="border-left-color: #fd7e14;">
      <div class="card-body">
        <div class="text-muted small">Pending Orders</div>
        <div class="num text-warning"><?= (int)$stat['pending_orders'] ?></div>
        <div class="small text-muted">Needs preparation</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card shadow-sm h-100" style="border-left-color: #0d6efd;">
      <div class="card-body">
        <div class="text-muted small">Active / Preparing</div>
        <div class="num text-primary"><?= (int)$stat['in_progress_orders'] ?></div>
        <div class="small text-muted">In kitchen / on the way</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card shadow-sm h-100" style="border-left-color: #198754;">
      <div class="card-body">
        <div class="text-muted small">Shop Revenue</div>
        <div class="num text-success"><?= money($stat['shop_revenue']) ?></div>
        <div class="small text-muted"><?= (int)$stat['delivered_orders'] ?> delivered</div>
      </div>
    </div>
  </div>
</div>

<?php if ($unassignedCount > 0): ?>
  <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 p-3 shadow-sm rounded-3">
    <div class="d-flex align-items-center gap-3">
      <div class="bg-warning text-dark p-2 rounded-circle fs-4">
        <i class="bi bi-bicycle"></i>
      </div>
      <div>
        <strong class="d-block text-dark fs-6">Attention: <?= $unassignedCount ?> Incoming Order<?= $unassignedCount === 1 ? '' : 's' ?> Need a Delivery Partner!</strong>
        <span class="small text-muted">Check available delivery boys below and assign a free rider so food can be picked up promptly.</span>
      </div>
    </div>
    <a href="#ordersTable" class="btn btn-warning btn-sm fw-bold px-3">
      <i class="bi bi-arrow-down-circle me-1"></i> Assign Delivery Boy Now
    </a>
  </div>
<?php endif; ?>

<!-- Live Available Delivery Boys Section -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 0.9rem; overflow: hidden;">
  <div class="card-header bg-white py-3 border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <h5 class="mb-0 fw-bold text-dark">
        <i class="bi bi-bicycle text-danger me-2"></i>Live Delivery Boys Fleet &amp; Availability
      </h5>
      <small class="text-muted">Check which delivery boys are free or busy before assigning incoming kitchen orders</small>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2">
        <i class="bi bi-circle-fill me-1" style="font-size: 0.55rem;"></i> <?= count($deliveryBoys) ?> Active Partner<?= count($deliveryBoys) === 1 ? '' : 's' ?>
      </span>
    </div>
  </div>
  <div class="card-body bg-light border-top">
    <div class="row g-3">
      <?php if (empty($deliveryBoys)): ?>
        <div class="col-12 text-center text-muted py-3">
          No delivery partners registered yet. <a href="<?= url('admin/users.php') ?>">Create delivery accounts in Admin panel</a>.
        </div>
      <?php else: ?>
        <?php foreach ($deliveryBoys as $dboy):
          $isFree = ((int)$dboy['active_deliveries'] === 0);
        ?>
        <div class="col-12 col-md-6 col-lg-6 col-xl-6">
          <div class="card border-0 shadow-sm h-100 bg-white">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                  <div class="position-relative">
                    <img src="<?= e(avatar_url($dboy)) ?>" class="rounded-circle border" width="46" height="46" alt="<?= e($dboy['name']) ?>">
                    <span class="position-absolute bottom-0 end-0 p-1 <?= $isFree ? 'bg-success' : 'bg-warning' ?> border border-white rounded-circle" title="<?= $isFree ? 'Free & Ready' : 'On Delivery' ?>"></span>
                  </div>
                  <div>
                    <strong class="d-block text-dark fs-6"><?= e($dboy['name']) ?></strong>
                    <small class="text-muted"><i class="bi bi-geo-alt"></i> <?= e($dboy['address'] ?: 'Durgapur Hub') ?></small>
                  </div>
                </div>
                <div class="text-end">
                  <?php if ($isFree): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success px-2 py-1">
                      <i class="bi bi-check-circle-fill me-1"></i> Available / Free
                    </span>
                    <div class="text-success small fw-semibold mt-1" style="font-size: 0.75rem;">Ready for pickup</div>
                  <?php else: ?>
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning px-2 py-1">
                      <i class="bi bi-bicycle me-1"></i> Busy (<?= (int)$dboy['active_deliveries'] ?> active)
                    </span>
                    <div class="text-muted small mt-1" style="font-size: 0.75rem;">In transit</div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="d-flex justify-content-between align-items-center pt-2 border-top small">
                <span>
                  <i class="bi bi-telephone-fill text-success me-1"></i>
                  <a href="tel:<?= e($dboy['phone']) ?>" class="text-decoration-none fw-semibold"><?= e($dboy['phone']) ?></a>
                </span>
                <span class="badge bg-light text-dark border">
                  <?= (int)$dboy['delivered_today'] ?> delivered today
                </span>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4" id="ordersTable">
  <div class="card-header bg-white py-3 border-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-receipt text-danger me-2"></i>Incoming & Recent Orders</h4>
        <small class="text-muted">See who placed the order and exactly which food items belong to your shop</small>
      </div>
      <div>
        <span class="badge bg-secondary"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?> shown</span>
      </div>
    </div>

    <form class="row g-2 mt-2" method="get">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input class="form-control" type="search" name="q" placeholder="Search by customer name, phone, order # or food..." value="<?= e($searchFilter) ?>">
        </div>
      </div>
      <div class="col-md-4">
        <select name="status" class="form-select" onchange="this.form.submit()">
          <option value="">All Order Statuses</option>
          <?php foreach ($validStatuses as $st): ?>
            <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $st))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-rx w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
        <?php if ($statusFilter !== '' || $searchFilter !== ''): ?>
          <a href="<?= url('shop/index.php') ?>" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0 table-hover">
      <thead class="table-light">
        <tr>
          <th style="width: 100px;">Order #</th>
          <th style="min-width: 230px;"><i class="bi bi-person-fill text-primary me-1"></i> Customer Details</th>
          <th style="min-width: 300px;"><i class="bi bi-egg-fried text-danger me-1"></i> Ordered Food</th>
          <th style="min-width: 120px;">Amount</th>
          <th style="min-width: 230px;"><i class="bi bi-bicycle text-danger me-1"></i> Delivery Partner</th>
          <th style="min-width: 160px;">Status &amp; Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($orders)): ?>
        <tr>
          <td colspan="6" class="text-center py-5">
            <div class="py-4">
              <i class="bi bi-inbox text-muted display-4"></i>
              <h5 class="mt-3 text-muted">No orders found for your shop.</h5>
              <p class="text-secondary small">When customers order food from your shop, they will appear here with customer contact and food details.</p>
              <a href="<?= url('index.php') ?>" target="_blank" class="btn btn-sm btn-outline-rx mt-2"><i class="bi bi-box-arrow-up-right me-1"></i> View Menu Site</a>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td class="align-top py-3">
            <span class="fw-bold fs-6">#<?= $o['id'] ?></span>
            <div class="small text-muted mt-1"><?= e(date('d M Y', strtotime($o['created_at']))) ?></div>
            <div class="small text-muted"><?= e(date('h:i A', strtotime($o['created_at']))) ?></div>
            <span class="badge bg-light text-dark border mt-1 text-uppercase"><?= e($o['payment_method']) ?></span>
          </td>

          <td class="align-top py-3">
            <div class="fw-bold text-dark fs-6"><?= e($o['customer_name']) ?></div>
            <div class="small mt-1">
              <i class="bi bi-telephone-fill text-success me-1"></i>
              <a href="tel:<?= e($o['order_phone']) ?>" class="text-decoration-none fw-semibold"><?= e($o['order_phone']) ?></a>
            </div>
            <div class="small text-muted">
              <i class="bi bi-envelope me-1"></i><?= e($o['customer_email']) ?>
            </div>
            <div class="small mt-2 p-2 rounded bg-light border">
              <div class="fw-semibold text-secondary"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Delivery Address:</div>
              <?= nl2br(e($o['delivery_address'])) ?>
            </div>
            <?php if (!empty($o['note'])): ?>
              <div class="small mt-1 text-danger fst-italic">
                <i class="bi bi-chat-quote-fill me-1"></i>"<?= e($o['note']) ?>"
              </div>
            <?php endif; ?>
          </td>

          <td class="align-top py-3">
            <div class="border rounded p-2 bg-white">
              <?php
                $items = $itemsByOrder[$o['id']] ?? [];
                if (empty($items)):
              ?>
                <span class="text-muted small">No shop items detected</span>
              <?php else: ?>
                <?php foreach ($items as $idx => $it): ?>
                  <div class="d-flex justify-content-between align-items-center py-2 <?= $idx > 0 ? 'border-top' : '' ?>">
                    <div>
                      <span class="<?= $it['is_veg'] ? 'veg-dot' : 'nonveg-dot' ?> me-2" title="<?= $it['is_veg'] ? 'Veg' : 'Non-veg' ?>"></span>
                      <strong class="text-dark"><?= e($it['food_name']) ?></strong>
                      <span class="badge bg-danger bg-opacity-10 text-danger ms-1">&times; <?= (int)$it['quantity'] ?></span>
                    </div>
                    <div class="text-end">
                      <span class="small text-muted"><?= money($it['price']) ?> ea</span>
                      <div class="fw-bold text-dark"><?= money($it['price'] * $it['quantity']) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </td>

          <td class="align-top py-3">
            <div class="fs-5 fw-bold text-success"><?= money($o['shop_subtotal']) ?></div>
            <div class="small text-muted">from <?= count($itemsByOrder[$o['id']] ?? []) ?> dish item(s)</div>
          </td>

          <td class="align-top py-3">
            <?php if (!empty($o['delivery_boy_id'])): ?>
              <div class="p-2 rounded bg-light border">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-bicycle fs-5 text-primary"></i>
                  <div>
                    <strong class="text-dark d-block"><?= e($o['delivery_boy_name']) ?></strong>
                    <a href="tel:<?= e($o['delivery_boy_phone']) ?>" class="small text-muted text-decoration-none">
                      <i class="bi bi-telephone-fill text-success me-1"></i><?= e($o['delivery_boy_phone']) ?>
                    </a>
                  </div>
                </div>
                <form method="post" class="mt-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="assign_rider">
                  <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                  <div class="input-group input-group-sm">
                    <select name="delivery_boy_id" class="form-select form-select-sm" style="font-size: 0.78rem;">
                      <option value="">-- Remove / Reassign --</option>
                      <?php foreach ($deliveryBoys as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= (int)$o['delivery_boy_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                          <?= e($r['name']) ?> (<?= (int)$r['active_deliveries'] === 0 ? '🟢 Free' : '🟡 ' . (int)$r['active_deliveries'] . ' active' ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-outline-secondary" title="Update Rider"><i class="bi bi-arrow-repeat"></i></button>
                  </div>
                </form>
              </div>
            <?php else: ?>
              <div class="p-2 rounded bg-warning bg-opacity-10 border border-warning">
                <span class="badge bg-warning text-dark mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>No Rider Assigned</span>
                <form method="post" class="d-flex flex-column gap-1 mt-1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="assign_rider">
                  <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                  <select name="delivery_boy_id" class="form-select form-select-sm" required style="font-size: 0.78rem;">
                    <option value="">-- Check &amp; Assign Rider --</option>
                    <?php foreach ($deliveryBoys as $r): ?>
                      <option value="<?= $r['id'] ?>">
                        <?= e($r['name']) ?> (<?= (int)$r['active_deliveries'] === 0 ? '🟢 Free' : '🟡 ' . (int)$r['active_deliveries'] . ' busy' ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-danger fw-semibold mt-1">
                    <i class="bi bi-send-check-fill me-1"></i> Assign Available Rider
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </td>

          <td class="align-top py-3">
            <div class="mb-2">
              <span class="badge status-<?= e($o['status']) ?> px-2 py-1 fs-7">
                <?= e(ucwords(str_replace('_', ' ', $o['status']))) ?>
              </span>
            </div>

            <form method="post" class="d-flex flex-column gap-1">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
              <select name="status" class="form-select form-select-sm" aria-label="Update Status">
                <?php foreach ($validStatuses as $st): ?>
                  <option value="<?= $st ?>" <?= $o['status'] === $st ? 'selected' : '' ?>>
                    <?= e(ucwords(str_replace('_', ' ', $st))) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-outline-secondary mt-1">
                <i class="bi bi-check2"></i> Update Status
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-trophy text-warning me-2"></i>Top Selling Dishes in Your Shop</h5>
        <a href="<?= url('shop/foods.php') ?>" class="small text-decoration-none">View all dishes &rarr;</a>
      </div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php if (empty($topDishes)): ?>
            <li class="list-group-item text-muted py-4 text-center">No sales recorded yet. Once orders are delivered, top dishes show up here.</li>
          <?php else: ?>
            <?php foreach ($topDishes as $td): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                <div>
                  <h6 class="mb-0 fw-semibold"><?= e($td['food_name']) ?></h6>
                  <small class="text-muted"><?= money($td['dish_revenue']) ?> total sales</small>
                </div>
                <span class="badge bg-danger rounded-pill px-3 py-2"><?= (int)$td['sold_qty'] ?> plates sold</span>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3 border-0">
        <h5 class="mb-0 fw-bold"><i class="bi bi-lightning-charge text-primary me-2"></i>Quick Shop Actions</h5>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="<?= url('shop/food_form.php') ?>" class="btn btn-rx py-2 text-start d-flex justify-content-between align-items-center">
            <span><i class="bi bi-plus-circle me-2"></i> Add a New Food Dish</span>
            <i class="bi bi-chevron-right"></i>
          </a>
          <a href="<?= url('shop/foods.php') ?>" class="btn btn-outline-dark py-2 text-start d-flex justify-content-between align-items-center">
            <span><i class="bi bi-card-checklist me-2"></i> Manage Menu &amp; Availability</span>
            <i class="bi bi-chevron-right"></i>
          </a>
          <a href="<?= url('shop/orders.php') ?>" class="btn btn-outline-secondary py-2 text-start d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clock-history me-2"></i> Full Order History</span>
            <i class="bi bi-chevron-right"></i>
          </a>
          <a href="<?= url('index.php') ?>" target="_blank" class="btn btn-outline-secondary py-2 text-start d-flex justify-content-between align-items-center">
            <span><i class="bi bi-eye me-2"></i> Preview Customer Menu</span>
            <i class="bi bi-box-arrow-up-right"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

