<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_delivery();
$deliveryArea = true;
$pageTitle = 'Completed Orders & Delivery History';
$pdo = db();

$q = trim($_GET['q'] ?? '');
$payMethod = $_GET['pay'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = ['o.delivery_boy_id = ?', "o.status = 'delivered'"];
$args = [$me['id']];

if ($q !== '') {
    $where[] = '(u.name LIKE ? OR o.id = ? OR o.delivery_address LIKE ?)';
    $args[] = "%$q%";
    $args[] = (int)$q;
    $args[] = "%$q%";
}

if (in_array($payMethod, ['cod', 'upi'], true)) {
    $where[] = 'o.payment_method = ?';
    $args[] = $payMethod;
}

$whereClause = implode(' AND ', $where);

// Count total matching
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id WHERE $whereClause");
$countStmt->execute($args);
$total = (int)$countStmt->fetchColumn();

// Fetch orders
$stmt = $pdo->prepare("SELECT o.*, u.name AS customer_name, u.email AS customer_email
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE $whereClause
    ORDER BY o.delivered_at DESC, o.id DESC
    LIMIT $perPage OFFSET $offset");
$stmt->execute($args);
$orders = $stmt->fetchAll();

// Stats
$totalDelivered = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_boy_id = {$me['id']} AND status = 'delivered'")->fetchColumn();
$todayDelivered = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_boy_id = {$me['id']} AND status = 'delivered' AND DATE(delivered_at) = CURDATE()")->fetchColumn();
$todayCodCash = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE delivery_boy_id = {$me['id']} AND status = 'delivered' AND payment_method = 'cod' AND DATE(delivered_at) = CURDATE()")->fetchColumn();
$lifetimeValue = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE delivery_boy_id = {$me['id']} AND status = 'delivered'")->fetchColumn();

$qs = http_build_query(array_filter(['q' => $q, 'pay' => $payMethod]));
$qs = $qs ? $qs . '&' : '';

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h2 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-success me-2"></i>Complete Orders &amp; Delivery History</h2>
    <small class="text-muted">Lifetime log of all food parcels safely delivered by you</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('delivery/active_tasks.php') ?>" class="btn btn-outline-danger btn-sm"><i class="bi bi-bicycle me-1"></i> Active Tasks</a>
    <a href="<?= url('delivery/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
  </div>
</div>

<!-- Stats Summary Row -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card stat-card shadow-sm h-100" style="border-left-color: #198754;">
      <div class="card-body">
        <span class="text-muted small">Total Delivered</span>
        <div class="num text-success mt-1"><?= $totalDelivered ?></div>
        <small class="text-muted">Lifetime orders</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card shadow-sm h-100" style="border-left-color: #0d6efd;">
      <div class="card-body">
        <span class="text-muted small">Delivered Today</span>
        <div class="num text-primary mt-1"><?= $todayDelivered ?></div>
        <small class="text-muted">Completed today</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card shadow-sm h-100" style="border-left-color: #fd7e14;">
      <div class="card-body">
        <span class="text-muted small">Today's COD Cash</span>
        <div class="num text-warning mt-1"><?= money($todayCodCash) ?></div>
        <small class="text-muted">Collected in cash</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card shadow-sm h-100" style="border-left-color: #6f42c1;">
      <div class="card-body">
        <span class="text-muted small">Total Parcel Value</span>
        <div class="num text-dark mt-1"><?= money($lifetimeValue) ?></div>
        <small class="text-muted">All time handled</small>
      </div>
    </div>
  </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-3">
    <form class="row g-2 align-items-center" method="get">
      <div class="col-md-6">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input class="form-control" type="search" name="q" placeholder="Search by customer name, order # or address..." value="<?= e($q) ?>">
        </div>
      </div>
      <div class="col-md-4">
        <select name="pay" class="form-select" onchange="this.form.submit()">
          <option value="">All Payment Types</option>
          <option value="cod" <?= $payMethod === 'cod' ? 'selected' : '' ?>>Cash on Delivery (COD)</option>
          <option value="upi" <?= $payMethod === 'upi' ? 'selected' : '' ?>>UPI on Delivery</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-rx w-100">Filter</button>
        <?php if ($q !== '' || $payMethod !== ''): ?>
          <a href="<?= url('delivery/history.php') ?>" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- History Table -->
<div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 1rem;">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Customer Name</th>
          <th>Delivery Address</th>
          <th>Delivered Date &amp; Time</th>
          <th>Payment</th>
          <th>Amount</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($orders)): ?>
        <tr>
          <td colspan="7" class="text-center py-5 text-muted">
            <i class="bi bi-inbox display-4 d-block mb-3"></i>
            <h5>No delivery records found</h5>
            <p class="small">Completed deliveries will be archived here automatically.</p>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-bold">
              #<?= $o['id'] ?>
            </td>
            <td>
              <strong class="text-dark d-block"><?= e($o['customer_name']) ?></strong>
              <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($o['phone']) ?></small>
            </td>
            <td class="small" style="max-width: 250px;">
              <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($o['delivery_address']) ?>
              <?php if (!empty($o['pincode'])): ?>
                <span class="badge bg-light text-dark border ms-1">PIN: <?= e($o['pincode']) ?></span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($o['delivered_at'])): ?>
                <i class="bi bi-calendar-check text-success me-1"></i><?= e(date('d M Y', strtotime($o['delivered_at']))) ?>
                <div class="text-muted"><?= e(date('h:i A', strtotime($o['delivered_at']))) ?></div>
              <?php else: ?>
                <?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($o['payment_method'] === 'cod'): ?>
                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">
                  <i class="bi bi-cash me-1"></i>COD Collected
                </span>
              <?php else: ?>
                <span class="badge bg-success bg-opacity-10 text-success border border-success">
                  <i class="bi bi-qr-code-scan me-1"></i>UPI Verified
                </span>
              <?php endif; ?>
            </td>
            <td class="fw-bold text-dark fs-6">
              <?= money($o['total']) ?>
            </td>
            <td>
              <span class="badge bg-success px-3 py-2">
                <i class="bi bi-check2-circle me-1"></i> Delivered
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($total > $perPage): ?>
  <div class="mt-4"><?= paginate($total, $page, $perPage, $qs) ?></div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

