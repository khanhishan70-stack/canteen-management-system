<?php
/**
 * header.php  -  Top of every page (HTML head + navigation bar).
 * Pages set $page_title before including this file.
 */
if (!isset($page_title)) {
    $page_title = APP_NAME;
}
$me     = current_user();
$cart_n = cart_count();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body<?= isset($body_class) && $body_class !== '' ? ' class="' . e($body_class) . '"' : ''; ?>>

<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <span class="brand-icon">&#127860;</span>
      <span><?= e(APP_NAME) ?></span>
    </a>

    <nav class="main-nav">
      <?php if ($me === null): ?>
        <span class="nav-system">&#127860; Order food &mdash; no login needed</span>
        <span class="wallet-badge" title="Demo wallet you get for free">
          &#128176; <?= e(money_format_balance(demo_balance())) ?>
        </span>
        <a href="<?= e(url('student/menu.php')) ?>">Menu</a>
        <a class="cart-link" href="<?= e(url('student/cart.php')) ?>">
          &#128722; Cart
          <?php if ($cart_n > 0): ?><span class="cart-count"><?= (int)$cart_n ?></span><?php endif; ?>
        </a>
        <a class="btn btn-small btn-staff" href="<?= e(url('staff/login.php')) ?>">Staff</a>
        <a class="btn btn-small btn-chef" href="<?= e(url('chef/login.php')) ?>">Chef</a>

      <?php elseif ($me['role'] === 'student'): ?>
        <span class="nav-system">&#127860; Student System</span>
        <span class="wallet-badge" title="Your demo wallet balance">
          &#128176; <?= e(money_format_balance((float)($me['balance'] ?? 0))) ?>
        </span>
        <a href="<?= e(url('student/menu.php')) ?>">Menu</a>
        <a class="cart-link" href="<?= e(url('student/cart.php')) ?>">
          &#128722; Cart
          <?php if ($cart_n > 0): ?><span class="cart-count"><?= (int)$cart_n ?></span><?php endif; ?>
        </a>
        <a href="<?= e(url('student/orders.php')) ?>">My Orders</a>
        <a href="<?= e(url('student/scan.php')) ?>">&#128247; Scan &amp; pay</a>

      <?php elseif ($me['role'] === 'manager'): ?>
        <span class="nav-system">&#128188; Staff System</span>
        <a href="<?= e(url('manager/dashboard.php')) ?>">Orders</a>
        <a href="<?= e(url('manager/menu.php')) ?>">Menu</a>
        <a href="<?= e(url('manager/verify.php')) ?>">Verify Pickup</a>
        <a href="<?= e(url('manager/reports.php')) ?>">Reports</a>

      <?php elseif ($me['role'] === 'chef'): ?>
        <span class="nav-system">&#127859; Chef System</span>
        <a href="<?= e(url('chef/kitchen.php')) ?>">Kitchen Screen</a>
      <?php endif; ?>
    </nav>

    <?php if ($me !== null): ?>
      <div class="user-box">
        <span class="user-name"><?= e(short_name($me['full_name'])) ?></span>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="inline-form">
          <?= csrf_field() ?>
          <button class="btn btn-small btn-ghost" type="submit">Log out</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</header>

<main class="container page">

<?php $flash = flash_get(); ?>
<?php if ($flash !== null): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
