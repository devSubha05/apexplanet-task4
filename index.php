<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Menu';

$q    = trim($_GET['q'] ?? '');
$cat  = (int)($_GET['cat'] ?? 0);
$type = $_GET['type'] ?? '';          // veg | nonveg
$sort = $_GET['sort'] ?? 'new';
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['f.is_available = 1']; $args = [];
if ($q !== '')  { $where[] = '(f.name LIKE ? OR f.description LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($cat > 0)   { $where[] = 'f.category_id = ?'; $args[] = $cat; }
if ($type === 'veg')    $where[] = 'f.is_veg = 1';
if ($type === 'nonveg') $where[] = 'f.is_veg = 0';
$w = implode(' AND ', $where);
$orderBy = ['new' => 'f.id DESC', 'low' => 'f.price ASC', 'high' => 'f.price DESC', 'name' => 'f.name ASC'][$sort] ?? 'f.id DESC';

$cnt = db()->prepare("SELECT COUNT(*) FROM foods f WHERE $w"); $cnt->execute($args);
$total = (int)$cnt->fetchColumn();

$off = ($page - 1) * PER_PAGE;
$st = db()->prepare("SELECT f.*, c.name AS cat FROM foods f JOIN categories c ON c.id=f.category_id WHERE $w ORDER BY $orderBy LIMIT " . PER_PAGE . " OFFSET $off");
$st->execute($args);
$foods = $st->fetchAll();
$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$qs = http_build_query(array_filter(['q' => $q, 'cat' => $cat ?: null, 'type' => $type, 'sort' => $sort !== 'new' ? $sort : null])) ;
$qs = $qs ? $qs . '&' : '';

require __DIR__ . '/includes/header.php';
?>
<section class="hero mb-4">
  <h1>Hot meals, fast to your door.</h1>
  <p class="lead mb-0">Browse the menu, add to cart and order in under a minute.</p>
</section>

<form class="row g-2 mb-4" method="get">
  <div class="col-12 col-md-4"><input class="form-control" type="search" name="q" placeholder="Search dishes" value="<?= e($q) ?>"></div>
  <div class="col-6 col-md-2">
    <select name="cat" class="form-select" aria-label="Category">
      <option value="0">All categories</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <select name="type" class="form-select" aria-label="Veg or non-veg">
      <option value="">Veg &amp; non-veg</option>
      <option value="veg" <?= $type === 'veg' ? 'selected' : '' ?>>Veg only</option>
      <option value="nonveg" <?= $type === 'nonveg' ? 'selected' : '' ?>>Non-veg only</option>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <select name="sort" class="form-select" aria-label="Sort">
      <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
      <option value="low" <?= $sort === 'low' ? 'selected' : '' ?>>Price: low to high</option>
      <option value="high" <?= $sort === 'high' ? 'selected' : '' ?>>Price: high to low</option>
      <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
    </select>
  </div>
  <div class="col-6 col-md-2 d-grid"><button class="btn btn-rx">Search</button></div>
</form>

<?php if (!$foods): ?>
  <div class="text-center text-muted py-5">No dishes match your search. <a href="<?= url('index.php') ?>">Clear filters</a></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($foods as $f): ?>
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="card food-card">
      <img src="<?= e(food_img($f['image'])) ?>" class="card-img-top" alt="<?= e($f['name']) ?>">
      <div class="card-body d-flex flex-column">
        <div class="small text-muted"><?= e($f['cat']) ?></div>
        <h5 class="card-title mb-1"><span class="<?= $f['is_veg'] ? 'veg-dot' : 'nonveg-dot' ?>" title="<?= $f['is_veg'] ? 'Veg' : 'Non-veg' ?>"></span> <?= e($f['name']) ?></h5>
        <p class="card-text small text-muted flex-grow-1"><?= e($f['description']) ?></p>
        <div class="d-flex justify-content-between align-items-center">
          <strong><?= money($f['price']) ?></strong>
          <form method="post" action="<?= url('cart.php') ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="food_id" value="<?= $f['id'] ?>">
            <button class="btn btn-sm btn-rx"><i class="bi bi-plus-lg"></i> Add</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<div class="mt-4"><?= paginate($total, $page, PER_PAGE, $qs) ?></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
