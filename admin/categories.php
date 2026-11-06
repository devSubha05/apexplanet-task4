<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$adminArea = true; $pageTitle = 'Categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? ''; $name = trim($_POST['name'] ?? ''); $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'add' || $action === 'rename') {
            if (mb_strlen($name) < 2 || mb_strlen($name) > 60) throw new RuntimeException('Category name must be 2-60 characters.');
            if ($action === 'add') db()->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
            else db()->prepare('UPDATE categories SET name=? WHERE id=?')->execute([$name, $id]);
            flash('success', 'Category saved.');
        } elseif ($action === 'delete') {
            db()->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
            flash('success', 'Category deleted.');
        }
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    } catch (PDOException $ex) {
        flash('danger', $ex->getCode() === '23000' ? 'That name already exists, or the category still has dishes. Move or delete them first.' : 'Database error.');
    }
    header('Location: ' . url('admin/categories.php')); exit;
}
$cats = db()->query('SELECT c.*, (SELECT COUNT(*) FROM foods f WHERE f.category_id=c.id) n FROM categories c ORDER BY c.name')->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-3">Categories</h2>
<form method="post" class="row g-2 mb-3 needs-validation" novalidate style="max-width:480px"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <div class="col"><input class="form-control" name="name" placeholder="New category" required minlength="2" maxlength="60" aria-label="New category name"></div>
  <div class="col-auto"><button class="btn btn-rx">Add</button></div>
</form>
<div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Name</th><th>Dishes</th><th class="text-end">Actions</th></tr></thead>
  <tbody><?php foreach ($cats as $c): ?>
    <tr>
      <td><form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= $c['id'] ?>">
        <input class="form-control form-control-sm" name="name" value="<?= e($c['name']) ?>" required minlength="2" maxlength="60" aria-label="Category name"><button class="btn btn-sm btn-outline-secondary">Rename</button></form></td>
      <td><?= (int)$c['n'] ?></td>
      <td class="text-end"><form method="post" class="d-inline" data-confirm="Delete category '<?= e($c['name']) ?>'?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
