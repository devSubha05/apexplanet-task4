<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$adminArea = true;
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$food = ['name' => '', 'description' => '', 'price' => '', 'category_id' => '', 'shop_id' => null, 'is_veg' => 1, 'is_available' => 1, 'image' => null];
if ($id) {
    $s = db()->prepare('SELECT * FROM foods WHERE id=?'); $s->execute([$id]);
    $food = $s->fetch();
    if (!$food) { http_response_code(404); exit('Dish not found.'); }
}
$pageTitle = $id ? 'Edit dish' : 'Add dish';
$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$shops = db()->query('SELECT id, name FROM shops ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? ''); $desc = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? ''; $catId = (int)($_POST['category_id'] ?? 0);
    $shopId = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;
    $isVeg = isset($_POST['is_veg']) ? 1 : 0; $avail = isset($_POST['is_available']) ? 1 : 0;

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name must be 2-100 characters.';
    if (mb_strlen($desc) > 500) $errors[] = 'Description must be 500 characters or fewer.';
    if (!is_numeric($price) || $price <= 0 || $price > 99999) $errors[] = 'Enter a valid price.';
    $chk = db()->prepare('SELECT id FROM categories WHERE id=?'); $chk->execute([$catId]);
    if (!$chk->fetch()) $errors[] = 'Choose a category.';
    [$img, $imgErr] = save_image($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'foods');
    if ($imgErr) $errors[] = $imgErr;

    $food = array_merge($food, ['name' => $name, 'description' => $desc, 'price' => $price, 'category_id' => $catId, 'shop_id' => $shopId, 'is_veg' => $isVeg, 'is_available' => $avail]);
    if (!$errors) {
        if ($id) {
            if ($img) { delete_image($food['image'], 'foods'); $food['image'] = $img; }
            db()->prepare('UPDATE foods SET category_id=?,shop_id=?,name=?,description=?,price=?,image=?,is_veg=?,is_available=? WHERE id=?')
                ->execute([$catId, $shopId, $name, $desc, $price, $food['image'], $isVeg, $avail, $id]);
            flash('success', 'Dish updated.');
        } else {
            db()->prepare('INSERT INTO foods (category_id,shop_id,name,description,price,image,is_veg,is_available) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$catId, $shopId, $name, $desc, $price, $img, $isVeg, $avail]);
            flash('success', 'Dish added.');
        }
        redirect('admin/foods.php');
    }
}
require __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-3"><?= e($pageTitle) ?></h2>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="card card-body border-0 shadow-sm needs-validation" style="max-width:720px" novalidate>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="mb-3"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" required minlength="2" maxlength="100" value="<?= e($food['name']) ?>"></div>
  <div class="row">
    <div class="col-md-4 mb-3"><label class="form-label" for="category_id">Category</label><select class="form-select" id="category_id" name="category_id" required><option value="">Choose...</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$food['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4 mb-3"><label class="form-label" for="shop_id">Kitchen / Shop <small class="text-muted">(Optional)</small></label><select class="form-select" id="shop_id" name="shop_id"><option value="">In-House / None</option><?php foreach ($shops as $sh): ?><option value="<?= $sh['id'] ?>" <?= (int)($food['shop_id'] ?? 0) === (int)$sh['id'] ? 'selected' : '' ?>><?= e($sh['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4 mb-3"><label class="form-label" for="price">Price (₹)</label><input class="form-control" type="number" step="0.01" min="1" max="99999" id="price" name="price" required value="<?= e($food['price']) ?>"></div>
  </div>
  <div class="mb-3"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="500"><?= e($food['description']) ?></textarea></div>
  <div class="mb-3 d-flex align-items-center gap-3">
    <img id="foodPreview" class="thumb" style="width:80px;height:80px" src="<?= e(food_img($food['image'])) ?>" alt="">
    <div><label class="form-label" for="image">Photo</label><input class="form-control" type="file" id="image" name="image" accept="image/png,image/jpeg,image/webp" data-preview="#foodPreview"><div class="form-text">JPG, PNG or WEBP, up to 2 MB.</div></div>
  </div>
  <div class="mb-3">
    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="is_veg" name="is_veg" <?= $food['is_veg'] ? 'checked' : '' ?>><label class="form-check-label" for="is_veg">Vegetarian</label></div>
    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="is_available" name="is_available" <?= $food['is_available'] ? 'checked' : '' ?>><label class="form-check-label" for="is_available">Available on the menu</label></div>
  </div>
  <div class="d-flex gap-2"><button class="btn btn-rx">Save dish</button><a class="btn btn-outline-secondary" href="<?= url('admin/foods.php') ?>">Cancel</a></div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
