<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Partner Shops & Kitchens';

$pdo = db();
$shopsStmt = $pdo->query("SELECT s.*, u.name AS owner_name,
    COUNT(f.id) AS total_dishes
FROM shops s
JOIN users u ON u.id = s.owner_id
LEFT JOIN foods f ON f.shop_id = s.id AND f.is_available = 1
GROUP BY s.id, u.name
ORDER BY s.id ASC");
$shops = $shopsStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="hero mb-4">
  <h1 class="display-6 fw-bold mb-2"><i class="bi bi-shop me-2 text-warning"></i>Partner Shops &amp; Cloud Kitchens</h1>
  <p class="lead mb-0 text-white-50">Explore authentic food prepared with love by our trusted culinary partners.</p>
</div>

<?php if (empty($shops)): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-shop display-1 text-secondary opacity-50 mb-3 d-block"></i>
    <p class="fs-5">No partner shops registered yet.</p>
    <a href="<?= url('index.php') ?>" class="btn btn-rx">Browse Main Menu</a>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($shops as $s): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0 food-card">
          <div class="card-body d-flex flex-column p-4">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="rounded-circle bg-danger bg-opacity-10 text-danger p-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                <i class="bi bi-shop fs-4"></i>
              </div>
              <div>
                <h4 class="card-title mb-0 fw-bold"><?= e($s['name']) ?></h4>
                <small class="text-muted"><i class="bi bi-egg-fried me-1"></i><?= (int)$s['total_dishes'] ?> active dishes</small>
              </div>
            </div>

            <p class="card-text text-muted small flex-grow-1 mb-3">
              <?= e($s['description'] ?: 'Authentic, hygienic, and fresh food prepared daily.') ?>
            </p>

            <div class="border-top pt-3 mb-3 small text-muted">
              <div class="mb-1"><i class="bi bi-geo-alt text-danger me-1"></i><?= e($s['address']) ?></div>
              <?php if (!empty($s['phone'])): ?>
                <div><i class="bi bi-telephone text-danger me-1"></i><?= e($s['phone']) ?></div>
              <?php endif; ?>
            </div>

            <div class="d-grid">
              <a href="<?= url('index.php?shop=' . $s['id']) ?>" class="btn btn-outline-rx fw-semibold">
                <i class="bi bi-book-half me-1"></i> View Dishes (<?= (int)$s['total_dishes'] ?>)
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

