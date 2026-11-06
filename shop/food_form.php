<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_shop_owner();
$shopArea = true;
$pdo = db();

$shop = owner_shop($me['id']);
if (!$shop) {
    redirect('shop/index.php');
}

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$food = ['name' => '', 'description' => '', 'price' => '', 'category_id' => '', 'is_veg' => 1, 'is_available' => 1, 'image' => null];

if ($id) {
    $s = $pdo->prepare('SELECT * FROM foods WHERE id=? AND shop_id=?');
    $s->execute([$id, $shop['id']]);
    $food = $s->fetch();
    if (!$food) {
        http_response_code(404);
        exit('Dish not found in your shop.');
    }
}

$pageTitle = $id ? 'Edit Dish' : 'Add New Dish';
$cats = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $catId = (int)($_POST['category_id'] ?? 0);
    $isVeg = isset($_POST['is_veg']) ? 1 : 0;
    $avail = isset($_POST['is_available']) ? 1 : 0;

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name must be 2-100 characters.';
    if (mb_strlen($desc) > 500) $errors[] = 'Description must be 500 characters or fewer.';
    if (!is_numeric($price) || $price <= 0 || $price > 99999) $errors[] = 'Enter a valid price.';

    $chk = $pdo->prepare('SELECT id FROM categories WHERE id=?');
    $chk->execute([$catId]);
    if (!$chk->fetch()) $errors[] = 'Choose a valid category.';

    [$img, $imgErr] = save_image($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'foods');
    if ($imgErr) $errors[] = $imgErr;

    $food = array_merge($food, [
        'name' => $name,
        'description' => $desc,
        'price' => $price,
        'category_id' => $catId,
        'is_veg' => $isVeg,
        'is_available' => $avail
    ]);

    if (!$errors) {
        if ($id) {
            if ($img) {
                delete_image($food['image'], 'foods');
                $food['image'] = $img;
            }
            $pdo->prepare('UPDATE foods SET category_id=?, name=?, description=?, price=?, image=?, is_veg=?, is_available=? WHERE id=? AND shop_id=?')
                ->execute([$catId, $name, $desc, $price, $food['image'], $isVeg, $avail, $id, $shop['id']]);
            flash('success', 'Dish updated successfully.');
        } else {
            $pdo->prepare('INSERT INTO foods (shop_id, category_id, name, description, price, image, is_veg, is_available) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$shop['id'], $catId, $name, $desc, $price, $img, $isVeg, $avail]);
            flash('success', 'New dish added to your shop.');
        }
        redirect('shop/foods.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="mb-3 d-flex align-items-center gap-2">
  <a href="<?= url('shop/foods.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Dishes</a>
  <h2 class="mb-0 fw-bold"><?= e($pageTitle) ?></h2>
</div>

<?php foreach ($errors as $er): ?>
  <div class="alert alert-danger py-2"><?= e($er) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="card card-body border-0 shadow-sm needs-validation" style="max-width:720px" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">

  <div class="mb-3">
    <label class="form-label fw-semibold" for="name">Dish Name</label>
    <input class="form-control" id="name" name="name" required minlength="2" maxlength="100" placeholder="e.g. Special Chicken Biryani" value="<?= e($food['name']) ?>">
    <div class="invalid-feedback">Enter a dish name (2-100 characters).</div>
  </div>

  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label fw-semibold" for="category_id">Category</label>
      <select class="form-select" id="category_id" name="category_id" required>
        <option value="">Choose category...</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (int)$food['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="invalid-feedback">Please choose a category.</div>
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label fw-semibold" for="price">Price (₹)</label>
      <input class="form-control" type="number" step="0.01" min="1" max="99999" id="price" name="price" required placeholder="e.g. 250" value="<?= e($food['price']) ?>">
      <div class="invalid-feedback">Enter a valid price.</div>
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label fw-semibold" for="description">Description</label>
    <textarea class="form-control" id="description" name="description" rows="3" maxlength="500" placeholder="Ingredients, preparation style or taste details..."><?= e($food['description']) ?></textarea>
    <div class="form-text">Up to 500 characters.</div>
  </div>

  <div class="mb-3 d-flex align-items-center gap-3 p-3 bg-light rounded border">
    <img id="foodPreview" class="thumb rounded border" style="width:80px;height:80px;object-fit:cover;" src="<?= e(food_img($food['image'])) ?>" alt="">
    <div class="flex-grow-1">
      <label class="form-label fw-semibold" for="image">Dish Photo</label>
      <input class="form-control" type="file" id="image" name="image" accept="image/png,image/jpeg,image/webp" data-preview="#foodPreview">
      <div class="form-text">JPG, PNG or WEBP, up to 2 MB. Leave empty to keep current photo.</div>
    </div>
  </div>

  <div class="mb-4">
    <div class="form-check form-switch mb-2">
      <input class="form-check-input" type="checkbox" role="switch" id="is_veg" name="is_veg" <?= $food['is_veg'] ? 'checked' : '' ?>>
      <label class="form-check-label fw-semibold" for="is_veg"><span class="veg-dot me-1"></span> Vegetarian Dish</label>
    </div>
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" role="switch" id="is_available" name="is_available" <?= $food['is_available'] ? 'checked' : '' ?>>
      <label class="form-check-label fw-semibold" for="is_available">Available for ordering immediately</label>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button class="btn btn-rx px-4"><i class="bi bi-check-lg me-1"></i> Save Dish</button>
    <a class="btn btn-outline-secondary" href="<?= url('shop/foods.php') ?>">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>

