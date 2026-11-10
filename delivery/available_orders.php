<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_delivery();
$deliveryArea = true;
$pageTitle = 'Available Orders to Claim';
$pdo = db();

// Handle claim action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($action === 'claim') {
        $stmt = $pdo->prepare("UPDATE orders SET delivery_boy_id = ?, status = IF(status='pending','preparing',status) WHERE id = ? AND (delivery_boy_id IS NULL OR delivery_boy_id = ?)");
        $stmt->execute([$me['id'], $orderId, $me['id']]);
        flash('success', "Order #$orderId claimed! It is now in your Active Tasks.");
        redirect('delivery/active_tasks.php');
    }
}

// Fetch unassigned orders waiting for pickup
$stmt = $pdo->prepare("SELECT o.*, u.name AS customer_name, u.email AS customer_email
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.delivery_boy_id IS NULL AND o.status IN ('pending', 'preparing')
    ORDER BY o.id ASC");
$stmt->execute();
$orders = $stmt->fetchAll();

// Fetch items for displayed orders
$itemsByOrder = [];
if (!empty($orders)) {
    $ids = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $itStmt = $pdo->prepare("SELECT oi.*, f.name AS dish_name, s.name AS shop_name, s.address AS shop_address, s.phone AS shop_phone
        FROM order_items oi
        LEFT JOIN foods f ON f.id = oi.food_id
        LEFT JOIN shops s ON s.id = f.shop_id
        WHERE oi.order_id IN ($in)
        ORDER BY oi.id ASC");
    $itStmt->execute($ids);
    foreach ($itStmt->fetchAll() as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam text-warning me-2"></i>Available Orders to Claim</h2>
    <small class="text-muted">Orders ready at partner kitchens waiting for a delivery partner</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('delivery/active_tasks.php') ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-bicycle me-1"></i> My Active Tasks</a>
    <a href="<?= url('delivery/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
  </div>
</div>

<?php if (empty($orders)): ?>
  <div class="card border-0 shadow-sm text-center py-5" style="border-radius: 1rem;">
    <div class="card-body py-5">
      <i class="bi bi-inbox text-muted display-3 d-block mb-3"></i>
      <h3 class="fw-bold text-dark">No New Orders Available Right Now</h3>
      <p class="text-muted mb-4" style="max-width: 480px; margin: 0 auto;">
        All orders have been picked up or claimed by delivery partners. When customers place new food orders, they will show up here immediately.
      </p>
      <a href="<?= url('delivery/active_tasks.php') ?>" class="btn btn-rx btn-lg px-4 shadow-sm">
        <i class="bi bi-bicycle me-1"></i> Go to My Active Deliveries
      </a>
    </div>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($orders as $o):
      $orderItems = $itemsByOrder[$o['id']] ?? [];
      $shopName = $orderItems[0]['shop_name'] ?? 'Durgapur Kitchen';
      $shopAddress = $orderItems[0]['shop_address'] ?? '45 Food Street, Durgapur';
      $shopPhone = $orderItems[0]['shop_phone'] ?? '9000000003';
    ?>
    <div class="col-12 col-lg-6">
      <div class="card border-0 shadow-sm h-100 overflow-hidden" style="border-radius: 1rem;">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-3 border-0">
          <div>
            <span class="fs-5 fw-bold text-dark">Order #<?= $o['id'] ?></span>
            <small class="text-muted ms-2"><i class="bi bi-clock me-1"></i>Placed <?= e(date('h:i A, d M', strtotime($o['created_at']))) ?></small>
          </div>
          <span class="badge bg-warning text-dark px-3 py-2 fw-semibold">
            <i class="bi bi-bell-fill me-1"></i> Ready to Claim
          </span>
        </div>

        <div class="card-body p-4 d-flex flex-column">
          <!-- Pickup Kitchen Info -->
          <div class="p-3 mb-3 rounded" style="background: #fff8f0; border-left: 4px solid #f7b32b;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="badge bg-warning text-dark"><i class="bi bi-shop me-1"></i>PICKUP FROM</span>
              <a href="tel:<?= e($shopPhone) ?>" class="btn btn-sm btn-outline-dark py-0 px-2 small"><i class="bi bi-telephone"></i> Call Kitchen</a>
            </div>
            <strong class="d-block text-dark fs-6"><?= e($shopName) ?></strong>
            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($shopAddress) ?></small>
          </div>

          <!-- Customer Dropoff Info -->
          <div class="p-3 mb-3 rounded" style="background: #f0f7ff; border-left: 4px solid #0d6efd;">
            <span class="badge bg-primary mb-1"><i class="bi bi-house-door me-1"></i>DELIVER TO AREA</span>
            <strong class="d-block text-dark fs-6"><?= e($o['customer_name']) ?></strong>
            <div class="text-dark small mt-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($o['delivery_address']) ?></div>
            <?php if (!empty($o['pincode'])): ?>
              <span class="badge bg-light text-dark border mt-1"><i class="bi bi-pin-map text-danger me-1"></i>PIN: <?= e($o['pincode']) ?></span>
            <?php endif; ?>
          </div>

          <!-- Items in Order -->
          <div class="mb-3">
            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Order Summary:</span>
            <ul class="list-group list-group-flush border-top border-bottom small">
              <?php foreach ($orderItems as $it): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1 bg-transparent">
                  <span><strong><?= (int)$it['quantity'] ?>&times;</strong> <?= e($it['food_name']) ?></span>
                  <span class="text-muted"><?= money($it['price'] * $it['quantity']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <!-- Payment Banner -->
          <div class="d-flex justify-content-between align-items-center p-3 mb-3 rounded <?= $o['payment_method'] === 'cod' ? 'bg-danger bg-opacity-10 border border-danger' : 'bg-success bg-opacity-10 border border-success' ?>">
            <div>
              <small class="text-muted d-block">Payment Method</small>
              <?php if ($o['payment_method'] === 'cod'): ?>
                <strong class="text-danger"><i class="bi bi-cash me-1"></i>Cash on Delivery (COD)</strong>
              <?php else: ?>
                <strong class="text-success"><i class="bi bi-qr-code-scan me-1"></i>UPI on Delivery</strong>
              <?php endif; ?>
            </div>
            <div class="text-end">
              <small class="text-muted d-block">Order Bill</small>
              <span class="fs-4 fw-bold text-dark"><?= money($o['total']) ?></span>
            </div>
          </div>

          <!-- Claim Button -->
          <div class="mt-auto pt-2">
            <form method="post" class="d-grid">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="claim">
              <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
              <button class="btn btn-rx btn-lg fw-bold shadow-sm py-2">
                <i class="bi bi-hand-thumbs-up-fill me-1"></i> Accept &amp; Claim Order #<?= $o['id'] ?>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

