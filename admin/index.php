<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$adminArea = true; $pageTitle = 'Admin dashboard';
$pdo = db();

$stat = $pdo->query("SELECT
  (SELECT COUNT(*) FROM users WHERE role='user') AS users,
  (SELECT COUNT(*) FROM users WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS active_users,
  (SELECT COUNT(*) FROM foods) AS foods,
  (SELECT COUNT(*) FROM orders) AS orders,
  (SELECT COUNT(*) FROM orders WHERE status='pending') AS pending,
  (SELECT COALESCE(SUM(total),0) FROM orders WHERE status<>'cancelled') AS revenue")->fetch();

// orders per day, last 7 days (fill gaps with zero)
$rows = $pdo->query("SELECT DATE(created_at) d, COUNT(*) c FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)")->fetchAll(PDO::FETCH_KEY_PAIR);
$labels = []; $counts = [];
for ($i = 6; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $labels[] = date('d M', strtotime($d)); $counts[] = (int)($rows[$d] ?? 0); }

$byStatus = $pdo->query('SELECT status, COUNT(*) c FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$top = $pdo->query("SELECT oi.food_name, SUM(oi.quantity) q FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.status<>'cancelled' GROUP BY oi.food_name ORDER BY q DESC LIMIT 5")->fetchAll();
$latest = $pdo->query('SELECT o.id,o.total,o.status,o.created_at,u.name FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC LIMIT 6')->fetchAll();

$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script><script>
new Chart(document.getElementById("perDay"),{type:"bar",data:{labels:' . json_encode($labels) . ',datasets:[{label:"Orders",data:' . json_encode($counts) . ',backgroundColor:"#c8102e"}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
new Chart(document.getElementById("byStatus"),{type:"doughnut",data:{labels:' . json_encode(array_map(fn($s) => str_replace('_', ' ', $s), array_keys($byStatus))) . ',datasets:[{data:' . json_encode(array_map('intval', array_values($byStatus))) . ',backgroundColor:["#6c757d","#fd7e14","#0d6efd","#198754","#dc3545"]}]}});
</script>';
require __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-3">Dashboard</h2>
<div class="row g-3 mb-4">
  <?php foreach ([['Revenue', money($stat['revenue'])], ['Total orders', $stat['orders']], ['Pending orders', $stat['pending']], ['Customers', $stat['users']], ['Active users (7 days)', $stat['active_users']], ['Menu items', $stat['foods']]] as [$l, $v]): ?>
  <div class="col-6 col-lg-4"><div class="card stat-card"><div class="card-body"><div class="text-muted small"><?= e($l) ?></div><div class="num"><?= e($v) ?></div></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-4 mb-4">
  <div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="card-body"><h6>Orders per day (last 7 days)</h6><canvas id="perDay" height="110"></canvas></div></div></div>
  <div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body"><h6>Orders by status</h6><?php if (!$byStatus): ?><p class="text-muted small">No orders yet.</p><?php endif; ?><canvas id="byStatus"></canvas></div></div></div>
</div>
<div class="row g-4">
  <div class="col-lg-6"><h5>Top selling dishes</h5>
    <ul class="list-group"><?php foreach ($top as $t): ?><li class="list-group-item d-flex justify-content-between"><?= e($t['food_name']) ?><span class="badge bg-secondary"><?= (int)$t['q'] ?> sold</span></li><?php endforeach; if (!$top): ?><li class="list-group-item text-muted">No sales yet.</li><?php endif; ?></ul></div>
  <div class="col-lg-6"><h5>Latest orders</h5>
    <ul class="list-group"><?php foreach ($latest as $o): ?><li class="list-group-item d-flex justify-content-between align-items-center"><span>#<?= $o['id'] ?> &middot; <?= e($o['name']) ?></span><span><?= money($o['total']) ?> <span class="badge status-<?= e($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></span></li><?php endforeach; if (!$latest): ?><li class="list-group-item text-muted">No orders yet.</li><?php endif; ?></ul></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
