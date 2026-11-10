<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_delivery();
$deliveryArea = true;
$pageTitle = 'Active Tasks & Deliveries';
$pdo = db();

// Handle status and assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($action === 'pickup') {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'out_for_delivery' WHERE id = ? AND delivery_boy_id = ?");
        $stmt->execute([$orderId, $me['id']]);
        flash('success', "Order #$orderId picked up. Status changed to Out for Delivery.");
        redirect('delivery/active_tasks.php');
    } elseif ($action === 'deliver') {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW() WHERE id = ? AND delivery_boy_id = ?");
        $stmt->execute([$orderId, $me['id']]);
        flash('success', "Order #$orderId successfully delivered! Great job!");
        redirect('delivery/history.php');
    } elseif ($action === 'release') {
        $stmt = $pdo->prepare("UPDATE orders SET delivery_boy_id = NULL WHERE id = ? AND delivery_boy_id = ? AND status <> 'delivered'");
        $stmt->execute([$orderId, $me['id']]);
        flash('warning', "Order #$orderId released back to available orders pool.");
        redirect('delivery/available_orders.php');
    }
}

// Fetch active assigned orders
$stmt = $pdo->prepare("SELECT o.*, u.name AS customer_name, u.email AS customer_email
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE o.delivery_boy_id = ? AND o.status IN ('preparing', 'out_for_delivery')
    ORDER BY o.id ASC");
$stmt->execute([$me['id']]);
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
    <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-bicycle text-danger me-2"></i>Active Tasks &amp; Deliveries</h2>
    <small class="text-muted">Food parcels currently assigned to you for pickup and dropoff</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('delivery/available_orders.php') ?>" class="btn btn-outline-warning text-dark btn-sm"><i class="bi bi-box-seam me-1"></i> Available Orders</a>
    <a href="<?= url('delivery/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
  </div>
</div>

<?php if (empty($orders)): ?>
  <div class="card border-0 shadow-sm text-center py-5" style="border-radius: 1rem;">
    <div class="card-body py-5">
      <i class="bi bi-check-circle-fill text-success display-3 d-block mb-3"></i>
      <h3 class="fw-bold text-dark">No Active Deliveries Right Now</h3>
      <p class="text-muted mb-4" style="max-width: 500px; margin: 0 auto;">
        You have no orders in transit or assigned for pickup. Check the Available Orders page to claim new incoming orders!
      </p>
      <a href="<?= url('delivery/available_orders.php') ?>" class="btn btn-rx btn-lg px-4 shadow-sm">
        <i class="bi bi-box-seam me-1"></i> View Available Orders to Claim
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
            <small class="text-muted ms-2"><i class="bi bi-clock me-1"></i><?= e(date('h:i A, d M', strtotime($o['created_at']))) ?></small>
          </div>
          <span class="badge fs-6 status-<?= e($o['status']) ?>">
            <?= e(ucwords(str_replace('_', ' ', $o['status']))) ?>
          </span>
        </div>

        <div class="card-body p-4 d-flex flex-column">
          <!-- Kitchen Pickup Info -->
          <div class="p-3 mb-3 rounded" style="background: #fff8f0; border-left: 4px solid #f7b32b;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="badge bg-warning text-dark"><i class="bi bi-shop me-1"></i>PICKUP KITCHEN</span>
              <a href="tel:<?= e($shopPhone) ?>" class="btn btn-sm btn-outline-dark py-0 px-2 small"><i class="bi bi-telephone"></i> Call Kitchen</a>
            </div>
            <strong class="d-block text-dark fs-6"><?= e($shopName) ?></strong>
            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($shopAddress) ?></small>
          </div>

          <!-- Customer Dropoff Info -->
          <div class="p-3 mb-3 rounded" style="background: #f0f7ff; border-left: 4px solid #0d6efd;">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="badge bg-primary"><i class="bi bi-house-door me-1"></i>DELIVER TO</span>
              <div class="d-flex gap-1">
                <a href="tel:<?= e($o['phone']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2 small"><i class="bi bi-telephone-fill"></i> Call</a>
                <a target="_blank" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($o['delivery_address'] . ($o['pincode'] ? ' ' . $o['pincode'] : '')) ?>" class="btn btn-sm btn-primary py-0 px-2 small"><i class="bi bi-map-fill"></i> Map</a>
              </div>
            </div>
            <strong class="d-block text-dark fs-6"><?= e($o['customer_name']) ?></strong>
            <div class="text-dark small mt-1"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($o['delivery_address']) ?></div>
            <?php if (!empty($o['pincode'])): ?>
              <span class="badge bg-light text-dark border mt-1"><i class="bi bi-pin-map text-danger me-1"></i>PIN: <?= e($o['pincode']) ?></span>
            <?php endif; ?>
            <?php if (!empty($o['note'])): ?>
              <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small"><i class="bi bi-chat-left-dots me-1"></i><strong>Note:</strong> <?= e($o['note']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Items in Order -->
          <div class="mb-3">
            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Items to Deliver:</span>
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
              <small class="text-muted d-block">Amount to Collect</small>
              <span class="fs-4 fw-bold <?= $o['payment_method'] === 'cod' ? 'text-danger' : 'text-success' ?>"><?= money($o['total']) ?></span>
            </div>
          </div>

          <!-- Actions -->
          <div class="mt-auto pt-3 border-top">
            <div class="d-flex gap-2">
              <?php if ($o['status'] === 'preparing' || $o['status'] === 'pending'): ?>
                <form method="post" class="flex-grow-1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="pickup">
                  <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                  <button class="btn btn-warning w-100 fw-bold btn-lg">
                    <i class="bi bi-box-arrow-right me-1"></i> Pick Up from Kitchen
                  </button>
                </form>
              <?php elseif ($o['status'] === 'out_for_delivery'): ?>
                <form method="post" class="flex-grow-1" data-confirm="Confirm that you have collected payment of <?= money($o['total']) ?> and delivered order #<?= $o['id'] ?> to customer <?= e($o['customer_name']) ?>?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="deliver">
                  <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                  <button class="btn btn-success w-100 fw-bold btn-lg shadow-sm">
                    <i class="bi bi-check-circle-fill me-1"></i> Mark as Delivered
                  </button>
                </form>
              <?php endif; ?>

              <form method="post" data-confirm="Return order #<?= $o['id'] ?> back to the available pool?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="release">
                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                <button class="btn btn-outline-secondary" title="Unassign / Release Order">
                  <i class="bi bi-x-circle"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

