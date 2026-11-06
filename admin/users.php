<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();
$adminArea = true; $pageTitle = 'Manage users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0); $action = $_POST['action'] ?? '';
    if ($id === (int)$admin['id']) {
        flash('warning', 'You cannot change or delete your own account here.');
    } elseif ($action === 'role') {
        db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user', $id]);
        flash('success', 'Role updated.');
    } elseif ($action === 'block') {
        db()->prepare("UPDATE users SET status = IF(status='active','blocked','active') WHERE id=?")->execute([$id]);
        flash('success', 'User status updated.');
    } elseif ($action === 'delete') {
        $s = db()->prepare('SELECT avatar FROM users WHERE id=?'); $s->execute([$id]);
        if ($u = $s->fetch()) { db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]); delete_image($u['avatar'], 'avatars'); flash('success', 'User deleted.'); }
    }
    header('Location: ' . url('admin/users.php')); exit;
}

$q = trim($_GET['q'] ?? ''); $role = $_GET['role'] ?? ''; $page = max(1, (int)($_GET['page'] ?? 1));
$where = ['1=1']; $args = [];
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if (in_array($role, ['user', 'admin'], true)) { $where[] = 'u.role=?'; $args[] = $role; }
$w = implode(' AND ', $where);
$c = db()->prepare("SELECT COUNT(*) FROM users u WHERE $w"); $c->execute($args); $total = (int)$c->fetchColumn();
$off = ($page - 1) * PER_PAGE;
$s = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) orders FROM users u WHERE $w ORDER BY u.id DESC LIMIT " . PER_PAGE . " OFFSET $off");
$s->execute($args); $rows = $s->fetchAll();
$qs = http_build_query(array_filter(['q' => $q, 'role' => $role])); $qs = $qs ? $qs . '&' : '';
require __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-3">Users</h2>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-5"><input class="form-control" type="search" name="q" placeholder="Search name or email" value="<?= e($q) ?>"></div>
  <div class="col-md-4"><select name="role" class="form-select" aria-label="Role"><option value="">All roles</option><option value="user" <?= $role === 'user' ? 'selected' : '' ?>>Customers</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admins</option></select></div>
  <div class="col-md-3 d-grid"><button class="btn btn-outline-rx">Filter</button></div>
</form>
<div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th></th><th>Name</th><th>Role</th><th>Orders</th><th>Last login</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $u): $self = (int)$u['id'] === (int)$admin['id']; ?>
    <tr>
      <td><img class="thumb rounded-circle" src="<?= e(avatar_url($u)) ?>" alt=""></td>
      <td><?= e($u['name']) ?><div class="small text-muted"><?= e($u['email']) ?></div></td>
      <td><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $u['id'] ?>">
        <select name="role" class="form-select form-select-sm" <?= $self ? 'disabled' : '' ?> aria-label="Role"><option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option><option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select>
        <?php if (!$self): ?><button class="btn btn-sm btn-outline-secondary">Set</button><?php endif; ?></form></td>
      <td><?= (int)$u['orders'] ?></td>
      <td class="small"><?= $u['last_login_at'] ? e(date('d M Y, h:i A', strtotime($u['last_login_at']))) : '&mdash;' ?></td>
      <td><span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>"><?= e($u['status']) ?></span></td>
      <td class="text-end text-nowrap">
        <?php if (!$self): ?>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="block"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-sm btn-outline-warning"><?= $u['status'] === 'active' ? 'Block' : 'Unblock' ?></button></form>
        <form method="post" class="d-inline" data-confirm="Delete <?= e($u['name']) ?> and all their orders?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        <?php else: ?><span class="small text-muted">You</span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<div class="mt-3"><?= paginate($total, $page, PER_PAGE, $qs) ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
