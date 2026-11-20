<?php require_once __DIR__ . '/auth.php'; $me = current_user(); $isAdminArea = !empty($adminArea); $isShopArea = !empty($shopArea); $isDeliveryArea = !empty($deliveryArea); ?>
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
<nav class="navbar navbar-expand-lg navbar-dark rx-nav <?= $isDeliveryArea ? 'delivery-nav' : '' ?> sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= url($isAdminArea ? 'admin/index.php' : ($isShopArea ? 'shop/index.php' : ($isDeliveryArea ? 'delivery/index.php' : 'index.php'))) ?>"><i class="bi bi-egg-fried me-1"></i><?= APP_NAME ?><?= $isAdminArea ? ' <small class="opacity-75">Admin</small>' : ($isShopArea ? ' <small class="opacity-75">Shop Portal</small>' : ($isDeliveryArea ? ' <small class="opacity-75 text-warning"><i class="bi bi-bicycle me-1"></i>Delivery Portal</small>' : '')) ?></a>

    <div class="d-flex align-items-center gap-2 d-lg-none">
      <?php if (!$isAdminArea && !$isShopArea && !$isDeliveryArea): ?>
        <a class="btn btn-sm btn-outline-light position-relative px-2 py-1" href="<?= url('cart.php') ?>" title="View Cart">
          <i class="bi bi-bag"></i>
          <span class="badge bg-warning text-dark cart-count-badge ms-1"><?= cart_count() ?></span>
        </a>
      <?php endif; ?>
      <button class="navbar-toggler border-0 shadow-none p-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarOffcanvas" aria-controls="navbarOffcanvas" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>

    <div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="navbarOffcanvas" aria-labelledby="navbarOffcanvasLabel">
      <div class="offcanvas-header border-bottom border-white border-opacity-10 d-lg-none">
        <h5 class="offcanvas-title fw-bold text-white d-flex align-items-center mb-0" id="navbarOffcanvasLabel">
          <i class="bi bi-egg-fried text-warning me-2 fs-4"></i><?= APP_NAME ?>
        </h5>
        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>

      <div class="offcanvas-body d-flex flex-column flex-lg-row justify-content-between p-3 p-lg-0">
        <ul class="navbar-nav me-auto mb-3 mb-lg-0">
          <?php if ($isAdminArea): ?>
            <?php $cur = basename($_SERVER['PHP_SELF'] ?? ''); ?>
            <li class="nav-item"><a class="nav-link <?= $cur === 'index.php' ? 'active' : '' ?>" href="<?= url('admin/index.php') ?>">Dashboard <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= $cur === 'foods.php' ? 'active' : '' ?>" href="<?= url('admin/foods.php') ?>">Foods <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= $cur === 'categories.php' ? 'active' : '' ?>" href="<?= url('admin/categories.php') ?>">Categories <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= $cur === 'orders.php' ? 'active' : '' ?>" href="<?= url('admin/orders.php') ?>">Orders <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= $cur === 'users.php' ? 'active' : '' ?>" href="<?= url('admin/users.php') ?>">Users <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link text-warning" href="<?= url('shop/index.php') ?>"><i class="bi bi-shop me-1"></i>Shop Portal <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link text-info" href="<?= url('delivery/index.php') ?>"><i class="bi bi-bicycle me-1"></i>Delivery Portal <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>">View site <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
          <?php elseif ($isShopArea): ?>
            <?php $cur = basename($_SERVER['PHP_SELF'] ?? ''); ?>
            <li class="nav-item"><a class="nav-link <?= $cur === 'index.php' ? 'active' : '' ?>" href="<?= url('shop/index.php') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= $cur === 'orders.php' ? 'active' : '' ?>" href="<?= url('shop/orders.php') ?>"><i class="bi bi-receipt me-1"></i>Orders <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= in_array($cur, ['foods.php', 'food_form.php']) ? 'active' : '' ?>" href="<?= url('shop/foods.php') ?>"><i class="bi bi-egg-fried me-1"></i>My Dishes <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>View Site <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
          <?php elseif ($isDeliveryArea): ?>
            <?php $currentScript = basename($_SERVER['PHP_SELF'] ?? ''); ?>
            <li class="nav-item"><a class="nav-link <?= $currentScript === 'index.php' ? 'active text-warning fw-bold' : '' ?>" href="<?= url('delivery/index.php') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= in_array($currentScript, ['active_tasks.php', 'active_deliveries.php']) ? 'active text-warning fw-bold' : '' ?>" href="<?= url('delivery/active_tasks.php') ?>"><i class="bi bi-bicycle me-1"></i>Active Tasks <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= in_array($currentScript, ['available_orders.php', 'available_to_claim.php']) ? 'active text-warning fw-bold' : '' ?>" href="<?= url('delivery/available_orders.php') ?>"><i class="bi bi-box-seam me-1"></i>Available to Claim <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= in_array($currentScript, ['history.php', 'completed_orders.php']) ? 'active text-warning fw-bold' : '' ?>" href="<?= url('delivery/history.php') ?>"><i class="bi bi-clock-history me-1"></i>Complete Orders <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>View Site <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
          <?php else: ?>
            <?php $cur = basename($_SERVER['PHP_SELF'] ?? ''); ?>
            <li class="nav-item"><a class="nav-link <?= $cur === 'index.php' ? 'active' : '' ?>" href="<?= url('index.php') ?>"><i class="bi bi-grid me-1"></i>Menu <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="nav-link <?= $cur === 'shops.php' ? 'active' : '' ?>" href="<?= url('shops.php') ?>"><i class="bi bi-shop me-1"></i>Shops <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <?php if ($me): ?>
              <li class="nav-item"><a class="nav-link <?= $cur === 'dashboard.php' ? 'active' : '' ?>" href="<?= url('dashboard.php') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
              <li class="nav-item"><a class="nav-link <?= $cur === 'orders.php' ? 'active' : '' ?>" href="<?= url('orders.php') ?>"><i class="bi bi-receipt me-1"></i>My orders <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
              <?php if ($me['role'] === 'admin'): ?><li class="nav-item"><a class="nav-link text-info" href="<?= url('admin/index.php') ?>"><i class="bi bi-shield-lock me-1"></i>Admin panel <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li><?php endif; ?>
              <?php if ($me['role'] === 'shop_owner' || $me['role'] === 'admin'): ?><li class="nav-item"><a class="nav-link text-warning fw-semibold" href="<?= url('shop/index.php') ?>"><i class="bi bi-shop me-1"></i><?= $me['role'] === 'admin' ? 'Shop Portal' : 'My Shop' ?> <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li><?php endif; ?>
              <?php if ($me['role'] === 'delivery' || $me['role'] === 'admin'): ?><li class="nav-item"><a class="nav-link text-warning fw-semibold" href="<?= url('delivery/index.php') ?>"><i class="bi bi-bicycle me-1"></i>Delivery Portal <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li><?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
        </ul>

        <ul class="navbar-nav align-items-lg-center gap-lg-2 border-top border-white border-opacity-10 pt-3 pt-lg-0 mt-2 mt-lg-0">
          <?php if (!$isAdminArea && !$isShopArea && !$isDeliveryArea): ?>
            <li class="nav-item d-none d-lg-block"><a class="nav-link" href="<?= url('cart.php') ?>"><i class="bi bi-bag"></i> Cart <span class="badge bg-warning text-dark cart-count-badge"><?= cart_count() ?></span></a></li>
          <?php endif; ?>
          <?php if ($me): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
                <img src="<?= e(avatar_url($me)) ?>" class="rounded-circle border border-warning" width="30" height="30" alt=""><?= e(explode(' ', $me['name'])[0]) ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="bi bi-person me-2"></i>My profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item mb-2 mb-lg-0"><a class="nav-link" href="<?= url('login.php') ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Log in <i class="bi bi-chevron-right d-lg-none text-white-50"></i></a></li>
            <li class="nav-item"><a class="btn btn-warning btn-sm w-100 fw-bold px-3 py-2 text-dark shadow-sm" href="<?= url('register.php') ?>"><i class="bi bi-person-plus me-1"></i>Sign up</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</nav>
<main class="container py-4 main-content flex-grow-1 d-flex flex-column">
<?php foreach (get_flash() as [$t, $m]): ?>
  <div class="alert alert-<?= e($t) ?> alert-dismissible fade show" role="alert"><?= e($m) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
