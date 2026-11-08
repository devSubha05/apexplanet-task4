<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_shop_owner();
$shopArea = true;
$pageTitle = 'My Dishes';
$pdo = db();

$shop = owner_shop($me['id']);
if (!$shop) {
    redirect('shop/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $s = $pdo->prepare('SELECT image FROM foods WHERE id=? AND shop_id=?');
        $s->execute([$id, $shop['id']]);
        if ($f = $s->fetch()) {
            $pdo->prepare('DELETE FROM foods WHERE id=? AND shop_id=?')->execute([$id, $shop['id']]);
            delete_image($f['image'], 'foods');
            flash('success', 'Dish deleted from your shop.');
        }
    }
    if ($action === 'toggle') {
        $pdo->prepare('UPDATE foods SET is_available = 1 - is_available WHERE id=? AND shop_id=?')->execute([$id, $shop['id']]);
        flash('success', 'Availability updated.');
    }
    header('Location: ' . url('shop/foods.php'));
    exit;
}

$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['f.shop_id=?'];
$args = [$shop['id']];

if ($q !== '') {
    $where[] = 'f.name LIKE ?';
    $args[] = "%$q%";
}
if ($cat) {
    $where[] = 'f.category_id=?';
    $args[] = $cat;
}
$w = implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM foods f WHERE $w");
$c->execute($args);
$total = (int)$c->fetchColumn();

$perPage = PER_PAGE;
$off = ($page - 1) * $perPage;

$s = $pdo->prepare("SELECT f.*, c.name cat FROM foods f JOIN categories c ON c.id=f.category_id WHERE $w ORDER BY f.id DESC LIMIT $perPage OFFSET $off");
$s->execute($args);
$foods = $s->fetchAll();

$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$qs = http_build_query(array_filter(['q' => $q, 'cat' => $cat ?: null]));
$qs = $qs ? $qs . '&' : '';

require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h2 class="mb-0 fw-bold">My Shop Dishes</h2>
    <small class="text-muted"><?= e($shop['name']) ?> &middot; <?= $total ?> total dish<?= $total === 1 ? '' : 'es' ?></small>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= url('shop/index.php') ?>"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
    <a class="btn btn-rx" href="<?= url('shop/food_form.php') ?>"><i class="bi bi-plus-lg"></i> Add New Dish</a>
  </div>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-5">
    <input class="form-control" type="search" name="q" placeholder="Search dish by name..." value="<?= e($q) ?>">
  </div>
  <div class="col-md-4">
    <select name="cat" class="form-select" aria-label="Category">
      <option value="0">All categories</option>
      <?php foreach ($cats as $ct): ?>
        <option value="<?= $ct['id'] ?>" <?= $cat === (int)$ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 d-grid">
    <button class="btn btn-outline-rx">Filter Dishes</button>
  </div>
</form>

<div class="table-responsive shadow-sm border-0">
  <table class="table align-middle mb-0 table-hover">
    <thead class="table-light">
      <tr>
        <th style="width: 70px;"></th>
        <th>Dish Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Status</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($foods as $f): ?>
      <tr>
        <td><img class="thumb rounded food-img-clickable" src="<?= e(food_img($f['image'])) ?>" alt="<?= e($f['name']) ?>" data-title="<?= e($f['name']) ?>" data-price="<?= money($f['price']) ?>" data-veg="<?= $f['is_veg'] ? '1' : '0' ?>" data-cat="<?= e($f['cat'] ?? '') ?>" data-desc="<?= e($f['description'] ?? '') ?>" title="Click to view full photo"></td>
        <td>
          <span class="<?= $f['is_veg'] ? 'veg-dot' : 'nonveg-dot' ?> me-1"></span>
          <strong class="text-dark"><?= e($f['name']) ?></strong>
          <?php if (!empty($f['description'])): ?>
            <div class="small text-muted text-truncate" style="max-width: 320px;"><?= e($f['description']) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="badge bg-light text-dark border"><?= e($f['cat']) ?></span></td>
        <td class="fw-semibold text-danger"><?= money($f['price']) ?></td>
        <td>
          <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $f['id'] ?>">
            <button class="badge border-0 <?= $f['is_available'] ? 'bg-success' : 'bg-secondary' ?>" title="Click to toggle availability">
              <?= $f['is_available'] ? 'Available' : 'Hidden' ?>
            </button>
          </form>
        </td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-secondary" href="<?= url('shop/food_form.php?id=' . $f['id']) ?>" title="Edit Dish"><i class="bi bi-pencil"></i> Edit</a>
          <form method="post" class="d-inline" data-confirm="Delete '<?= e($f['name']) ?>'? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $f['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; if (!$foods): ?>
      <tr><td colspan="6" class="text-center text-muted py-5">No dishes found in your shop. <a href="<?= url('shop/food_form.php') ?>">Add your first dish</a>.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="mt-3"><?= paginate($total, $page, $perPage, $qs) ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

