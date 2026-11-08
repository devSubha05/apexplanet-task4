<?php require_once __DIR__ . '/auth.php'; $me = current_user(); $isAdminArea = !empty($adminArea); $isShopArea = !empty($shopArea); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Home') ?> | <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark rx-nav sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= url($isAdminArea ? 'admin/index.php' : ($isShopArea ? 'shop/index.php' : 'index.php')) ?>"><i class="bi bi-egg-fried me-1"></i><?= APP_NAME ?><?= $isAdminArea ? ' <small class="opacity-75">Admin</small>' : ($isShopArea ? ' <small class="opacity-75">Shop Portal</small>' : '') ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <?php if ($isAdminArea): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('admin/index.php') ?>">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('admin/foods.php') ?>">Foods</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('admin/categories.php') ?>">Categories</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('admin/orders.php') ?>">Orders</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('admin/users.php') ?>">Users</a></li>
          <li class="nav-item"><a class="nav-link text-warning" href="<?= url('shop/index.php') ?>"><i class="bi bi-shop me-1"></i>Shop Portal</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>">View site</a></li>
        <?php elseif ($isShopArea): ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('shop/index.php') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('shop/orders.php') ?>"><i class="bi bi-receipt me-1"></i>Orders</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('shop/foods.php') ?>"><i class="bi bi-egg-fried me-1"></i>My Dishes</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>View Site</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>">Menu</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= url('shops.php') ?>"><i class="bi bi-shop me-1"></i>Shops</a></li>
          <?php if ($me): ?>
            <li class="nav-item"><a class="nav-link" href="<?= url('dashboard.php') ?>">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= url('orders.php') ?>">My orders</a></li>
            <?php if ($me['role'] === 'admin'): ?><li class="nav-item"><a class="nav-link" href="<?= url('admin/index.php') ?>">Admin panel</a></li><?php endif; ?>
            <?php if ($me['role'] === 'shop_owner' || $me['role'] === 'admin'): ?><li class="nav-item"><a class="nav-link text-warning fw-semibold" href="<?= url('shop/index.php') ?>"><i class="bi bi-shop me-1"></i><?= $me['role'] === 'admin' ? 'Shop Portal' : 'My Shop' ?></a></li><?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav align-items-lg-center gap-lg-2">
        <?php if (!$isAdminArea && !$isShopArea): ?>
        <li class="nav-item"><a class="nav-link" href="<?= url('cart.php') ?>"><i class="bi bi-bag"></i> Cart <span class="badge bg-warning text-dark"><?= cart_count() ?></span></a></li>
        <?php endif; ?>
        <?php if ($me): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
              <img src="<?= e(avatar_url($me)) ?>" class="rounded-circle" width="28" height="28" alt=""><?= e(explode(' ', $me['name'])[0]) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= url('profile.php') ?>">My profile</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= url('logout.php') ?>">Log out</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= url('login.php') ?>">Log in</a></li>
          <li class="nav-item"><a class="btn btn-warning btn-sm" href="<?= url('register.php') ?>">Sign up</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="container py-4 main-content flex-grow-1 d-flex flex-column">
<?php foreach (get_flash() as [$t, $m]): ?>
  <div class="alert alert-<?= e($t) ?> alert-dismissible fade show" role="alert"><?= e($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
