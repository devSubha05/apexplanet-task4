<?php
require_once __DIR__ . '/includes/auth.php';
$me = require_login();
$pageTitle = 'Dashboard';

$st = db()->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(CASE WHEN status<>'cancelled' THEN total END),0) AS spent,
  SUM(status IN ('pending','preparing','out_for_delivery')) AS active FROM orders WHERE user_id=?");
$st->execute([$me['id']]); $stats = $st->fetch();
$recent = db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 5'); $recent->execute([$me['id']]);
$recent = $recent->fetchAll();
$fav = db()->prepare("SELECT oi.food_name, SUM(oi.quantity) q FROM order_items oi JOIN orders o ON o.id=oi.order_id
  WHERE o.user_id=? AND o.status<>'cancelled' GROUP BY oi.food_name ORDER BY q DESC LIMIT 3");
$fav->execute([$me['id']]); $fav = $fav->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center gap-3 mb-4">
  <img src="<?= e(avatar_url($me)) ?>" class="rounded-circle" width="64" height="64" alt="">
  <div><h2 class="mb-0">Hello, <?= e(explode(' ', $me['name'])[0]) ?></h2><span class="text-muted small"><?= e($me['email']) ?></span></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-sm-4"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Total orders</div><div class="num"><?= (int)$stats['orders'] ?></div></div></div></div>
  <div class="col-sm-4"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Active orders</div><div class="num"><?= (int)$stats['active'] ?></div></div></div></div>
  <div class="col-sm-4"><div class="card stat-card"><div class="card-body"><div class="text-muted small">Total spent</div><div class="num"><?= money($stats['spent']) ?></div></div></div></div>
</div>
<div class="row g-4">
  <div class="col-lg-8">
    <h5>Recent orders</h5>
    <?php if (!$recent): ?><p class="text-muted">Nothing here yet. <a href="<?= url('index.php') ?>">Start your first order</a>.</p><?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><tbody>
      <?php foreach ($recent as $o): ?><tr><td>#<?= $o['id'] ?></td><td><?= e(date('d M, h:i A', strtotime($o['created_at']))) ?></td><td><?= money($o['total']) ?></td>
      <td><span class="badge status-<?= e($o['status']) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td><td><a href="<?= url('orders.php?id=' . $o['id']) ?>">View</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div>
  <div class="col-lg-4">
    <h5>Your favourites</h5>
    <?php if (!$fav): ?><p class="text-muted">Order a few dishes and your favourites show up here.</p><?php else: ?>
    <ul class="list-group"><?php foreach ($fav as $f): ?><li class="list-group-item d-flex justify-content-between"><?= e($f['food_name']) ?><span class="badge bg-secondary"><?= (int)$f['q'] ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
