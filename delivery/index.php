<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_delivery();
$deliveryArea = true;
$pageTitle = 'Delivery Dashboard';
$pdo = db();

// Handle quick actions from dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($action === 'claim') {
        $stmt = $pdo->prepare("UPDATE orders SET delivery_boy_id = ?, status = IF(status='pending','preparing',status) WHERE id = ? AND (delivery_boy_id IS NULL OR delivery_boy_id = ?)");
        $stmt->execute([$me['id'], $orderId, $me['id']]);
        flash('success', "Order #$orderId claimed! It is now in your Active Tasks.");
        redirect('delivery/active_tasks.php');
    } elseif ($action === 'pickup') {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'out_for_delivery' WHERE id = ? AND delivery_boy_id = ?");
        $stmt->execute([$orderId, $me['id']]);
        flash('success', "Order #$orderId picked up. Status changed to Out for Delivery.");
        redirect('delivery/active_tasks.php');
    } elseif ($action === 'deliver') {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND delivery_boy_id = ?");
        $stmt->execute([$orderId, $me['id']]);
        flash('success', "Order #$orderId successfully delivered! Great job!");
        redirect('delivery/history.php');
    }
}

// Stats for delivery partner
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_boy_id = {$me['id']} AND status IN ('preparing', 'out_for_delivery')")->fetchColumn();
$availableCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_boy_id IS NULL AND status IN ('pending', 'preparing')")->fetchColumn();
$completedCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_boy_id = {$me['id']} AND status = 'delivered'")->fetchColumn();
$todayCodCash = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE delivery_boy_id = {$me['id']} AND status = 'delivered' AND payment_method = 'cod' AND DATE(delivered_at) = CURDATE()")->fetchColumn();

// Fetch up to 4 active tasks for quick view
$activeOrders = $pdo->query("SELECT o.*, u.name AS customer_name, u.email AS customer_email
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.delivery_boy_id = {$me['id']} AND o.status IN ('preparing', 'out_for_delivery')
    ORDER BY o.id ASC LIMIT 4")->fetchAll();

// Fetch up to 4 available orders for quick claim
$availableOrders = $pdo->query("SELECT o.*, u.name AS customer_name, u.email AS customer_email
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.delivery_boy_id IS NULL AND o.status IN ('pending', 'preparing')
    ORDER BY o.id ASC LIMIT 4")->fetchAll();

// Fetch shop info for active orders
$activeOrderIds = array_column($activeOrders, 'id');
$itemsByOrder = [];
if (!empty($activeOrderIds)) {
    $in = implode(',', $activeOrderIds);
    $itStmt = $pdo->query("SELECT oi.*, s.name AS shop_name, s.address AS shop_address, s.phone AS shop_phone
        FROM order_items oi
        LEFT JOIN foods f ON f.id = oi.food_id
        LEFT JOIN shops s ON s.id = f.shop_id
        WHERE oi.order_id IN ($in)
        ORDER BY oi.id ASC");
    foreach ($itStmt->fetchAll() as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<!-- Partner Header Card -->
<div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #2b1b17 0%, #4a1f18 100%); color: #fff; border-radius: 1rem;">
  <div class="card-body p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="position-relative">
          <img src="<?= e(avatar_url($me)) ?>" class="rounded-circle border border-warning border-3 shadow" width="70" height="70" alt="Delivery Partner">
          <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle p-2" title="Online & Ready"></span>
        </div>
        <div>
          <div class="badge bg-warning text-dark fw-bold mb-1"><i class="bi bi-bicycle me-1"></i>Delivery Partner Dashboard</div>
          <h3 class="fw-bold mb-0 text-white"><?= e($me['name']) ?></h3>
          <small class="text-white-50"><i class="bi bi-geo-alt me-1"></i><?= e($me['address'] ?: 'Durgapur Central Hub') ?> &middot; <i class="bi bi-telephone me-1"></i><?= e($me['phone']) ?></small>
        </div>
      </div>
      <div class="text-end">
        <span class="badge bg-success bg-opacity-25 text-success border border-success px-3 py-2 fs-6">
          <i class="bi bi-check-circle-fill me-1"></i> On Duty &middot; Available
        </span>
      </div>
    </div>
  </div>
</div>

<!-- 4 Navigation KPI Cards (Linking directly to separate dedicated pages) -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <a href="<?= url('delivery/active_tasks.php') ?>" class="text-decoration-none">
      <div class="card stat-card shadow-sm h-100 hover-card" style="border-left-color: #0d6efd; transition: transform 0.2s;">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small fw-bold text-uppercase">Active Tasks</span>
            <i class="bi bi-bicycle fs-4 text-primary"></i>
          </div>
          <div class="num text-primary mt-1"><?= $activeCount ?></div>
          <div class="d-flex justify-content-between align-items-center mt-2 small text-primary fw-semibold">
            <span>In transit &rarr;</span>
            <span class="badge bg-primary-subtle text-primary border">View Page</span>
          </div>
        </div>
      </div>
    </a>
  </div>

  <div class="col-6 col-lg-3">
    <a href="<?= url('delivery/available_orders.php') ?>" class="text-decoration-none">
      <div class="card stat-card shadow-sm h-100 hover-card" style="border-left-color: #fd7e14; transition: transform 0.2s;">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small fw-bold text-uppercase">Available to Claim</span>
            <i class="bi bi-box-seam fs-4 text-warning"></i>
          </div>
          <div class="num text-warning mt-1"><?= $availableCount ?></div>
          <div class="d-flex justify-content-between align-items-center mt-2 small text-warning fw-semibold">
            <span>Ready for pickup &rarr;</span>
            <span class="badge bg-warning-subtle text-dark border">Claim Orders</span>
          </div>
        </div>
      </div>
    </a>
  </div>

  <div class="col-6 col-lg-3">
    <a href="<?= url('delivery/history.php') ?>" class="text-decoration-none">
      <div class="card stat-card shadow-sm h-100 hover-card" style="border-left-color: #198754; transition: transform 0.2s;">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted small fw-bold text-uppercase">Complete Orders</span>
            <i class="bi bi-check2-circle fs-4 text-success"></i>
          </div>
          <div class="num text-success mt-1"><?= $completedCount ?></div>
          <div class="d-flex justify-content-between align-items-center mt-2 small text-success fw-semibold">
            <span>Delivery history &rarr;</span>
            <span class="badge bg-success-subtle text-success border">History</span>
          </div>
        </div>
      </div>
    </a>
  </div>

  <div class="col-6 col-lg-3">
    <div class="card stat-card shadow-sm h-100" style="border-left-color: #c8102e;">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <span class="text-muted small fw-bold text-uppercase">Today's COD Cash</span>
          <i class="bi bi-cash-stack fs-4 text-danger"></i>
        </div>
        <div class="num text-dark mt-1"><?= money($todayCodCash) ?></div>
        <div class="mt-2 small text-muted">
          Cash collected on hand
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Section 1: Active Tasks Quick Overview -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
  <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
    <div>
      <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-bicycle text-primary me-2"></i>My Active Deliveries</h5>
      <small class="text-muted">Parcels currently assigned to you</small>
    </div>
    <a href="<?= url('delivery/active_tasks.php') ?>" class="btn btn-sm btn-outline-primary fw-semibold">
      Open Active Tasks Page (<?= $activeCount ?>) &rarr;
    </a>
  </div>
  <div class="card-body p-4 bg-light border-top">
    <?php if (empty($activeOrders)): ?>
      <div class="text-center py-4 text-muted">
        <i class="bi bi-check-circle fs-2 text-success d-block mb-2"></i>
        <p class="mb-2 fw-semibold">You have no active deliveries right now.</p>
        <a href="<?= url('delivery/available_orders.php') ?>" class="btn btn-sm btn-rx">View Available Orders to Claim &rarr;</a>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($activeOrders as $ao):
          $shopInfo = $itemsByOrder[$ao['id']][0] ?? [];
        ?>
        <div class="col-12 col-md-6">
          <div class="card border-0 shadow-sm h-100 bg-white p-3" style="border-radius: 0.8rem;">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fw-bold fs-6">Order #<?= $ao['id'] ?></span>
              <span class="badge status-<?= e($ao['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $ao['status']))) ?></span>
            </div>
            <div class="small mb-2">
              <span class="badge bg-warning text-dark"><i class="bi bi-shop me-1"></i>Pickup:</span>
              <strong><?= e($shopInfo['shop_name'] ?? 'Durgapur Kitchen') ?></strong>
            </div>
            <div class="small mb-3">
              <span class="badge bg-primary"><i class="bi bi-house-door me-1"></i>Deliver:</span>
              <?= e($ao['customer_name']) ?> &middot; <span class="text-muted"><?= e($ao['delivery_address']) ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
              <span class="fw-bold <?= $ao['payment_method'] === 'cod' ? 'text-danger' : 'text-success' ?>">
                <?= money($ao['total']) ?> (<?= strtoupper($ao['payment_method']) ?>)
              </span>
              <?php if ($ao['status'] === 'preparing'): ?>
                <form method="post" action="<?= url('delivery/active_tasks.php') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="pickup">
                  <input type="hidden" name="order_id" value="<?= $ao['id'] ?>">
                  <button class="btn btn-sm btn-warning fw-bold"><i class="bi bi-box-arrow-right me-1"></i> Pick Up</button>
                </form>
              <?php else: ?>
                <a href="<?= url('delivery/active_tasks.php') ?>" class="btn btn-sm btn-success fw-bold"><i class="bi bi-check2-circle me-1"></i> Deliver</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Section 2: Available Orders Quick Claim -->
<div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
  <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
    <div>
      <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam text-warning me-2"></i>Available Orders Ready for Pickup</h5>
      <small class="text-muted">Unassigned kitchen orders ready to be claimed</small>
    </div>
    <a href="<?= url('delivery/available_orders.php') ?>" class="btn btn-sm btn-outline-warning text-dark fw-semibold">
      Open Available Orders Page (<?= $availableCount ?>) &rarr;
    </a>
  </div>
  <div class="card-body p-4 bg-light border-top">
    <?php if (empty($availableOrders)): ?>
      <div class="text-center py-4 text-muted">
        <i class="bi bi-inbox fs-2 text-muted d-block mb-2"></i>
        <p class="mb-0">No new orders waiting for pickup right now.</p>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($availableOrders as $avo): ?>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="card border-0 shadow-sm h-100 bg-white p-3" style="border-radius: 0.8rem;">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fw-bold">Order #<?= $avo['id'] ?></span>
              <span class="badge bg-warning text-dark">Ready</span>
            </div>
            <div class="small text-muted mb-2 text-truncate" title="<?= e($avo['delivery_address']) ?>">
              <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($avo['delivery_address']) ?>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
              <span class="fw-bold text-dark"><?= money($avo['total']) ?></span>
              <form method="post" action="<?= url('delivery/available_orders.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="claim">
                <input type="hidden" name="order_id" value="<?= $avo['id'] ?>">
                <button class="btn btn-sm btn-rx fw-bold"><i class="bi bi-hand-thumbs-up me-1"></i> Claim</button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
