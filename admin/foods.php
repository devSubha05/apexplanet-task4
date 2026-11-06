<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$adminArea = true; $pageTitle = 'Manage foods';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $s = db()->prepare('SELECT image FROM foods WHERE id=?'); $s->execute([$id]);
        if ($f = $s->fetch()) {
            db()->prepare('DELETE FROM foods WHERE id=?')->execute([$id]);
            delete_image($f['image'], 'foods');
            flash('success', 'Dish deleted.');
        }
    }
    if (($_POST['action'] ?? '') === 'toggle') {
        db()->prepare('UPDATE foods SET is_available = 1 - is_available WHERE id=?')->execute([$id]);
        flash('success', 'Availability updated.');
    }
    header('Location: ' . url('admin/foods.php')); exit;
}

$q = trim($_GET['q'] ?? ''); $cat = (int)($_GET['cat'] ?? 0); $page = max(1, (int)($_GET['page'] ?? 1));
$where = ['1=1']; $args = [];
if ($q !== '') { $where[] = 'f.name LIKE ?'; $args[] = "%$q%"; }
if ($cat) { $where[] = 'f.category_id=?'; $args[] = $cat; }
$w = implode(' AND ', $where);
$c = db()->prepare("SELECT COUNT(*) FROM foods f WHERE $w"); $c->execute($args); $total = (int)$c->fetchColumn();
$off = ($page - 1) * PER_PAGE;
$s = db()->prepare("SELECT f.*, c.name cat, s.name AS shop_name FROM foods f JOIN categories c ON c.id=f.category_id LEFT JOIN shops s ON s.id=f.shop_id WHERE $w ORDER BY f.id DESC LIMIT " . PER_PAGE . " OFFSET $off");
$s->execute($args); $foods = $s->fetchAll();
$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$qs = http_build_query(array_filter(['q' => $q, 'cat' => $cat ?: null])); $qs = $qs ? $qs . '&' : '';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h2 class="mb-0">Foods</h2><a class="btn btn-rx" href="<?= url('admin/food_form.php') ?>"><i class="bi bi-plus-lg"></i> Add dish</a>
</div>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input class="form-control" type="search" name="q" placeholder="Search by name" value="<?= e($q) ?>"></div>
  <div class="col-md-4"><select name="cat" class="form-select" aria-label="Category"><option value="0">All categories</option><?php foreach ($cats as $ct): ?><option value="<?= $ct['id'] ?>" <?= $cat === (int)$ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3 d-grid"><button class="btn btn-outline-rx">Filter</button></div>
</form>
<div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($foods as $f): ?>
    <tr>
      <td><img class="thumb" src="<?= e(food_img($f['image'])) ?>" alt=""></td>
      <td>
        <span class="<?= $f['is_veg'] ? 'veg-dot' : 'nonveg-dot' ?>"></span> <?= e($f['name']) ?>
        <?php if (!empty($f['shop_name'])): ?>
          <span class="badge bg-light text-dark border small ms-1"><i class="bi bi-shop text-danger me-1"></i><?= e($f['shop_name']) ?></span>
        <?php endif; ?>
      </td>
      <td><?= e($f['cat']) ?></td><td><?= money($f['price']) ?></td>
      <td><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $f['id'] ?>">
        <button class="badge border-0 <?= $f['is_available'] ? 'bg-success' : 'bg-secondary' ?>" title="Click to toggle"><?= $f['is_available'] ? 'Available' : 'Hidden' ?></button></form></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('admin/food_form.php?id=' . $f['id']) ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" class="d-inline" data-confirm="Delete '<?= e($f['name']) ?>'? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $f['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
      </td>
    </tr>
  <?php endforeach; if (!$foods): ?><tr><td colspan="6" class="text-center text-muted py-4">No dishes found.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<div class="mt-3"><?= paginate($total, $page, PER_PAGE, $qs) ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
