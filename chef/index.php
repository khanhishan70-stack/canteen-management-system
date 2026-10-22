<?php
/**
 * chef/index.php  -  CHEF SYSTEM landing page.
 * This is the separate live link for the chef:
 *   .../chef/
 * It explains exactly what the chef will SEE on the kitchen screen,
 * then offers the chef login.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = current_user();
$page_title = 'Chef System';
require __DIR__ . '/../includes/header.php';
?>

<div class="hero">
  <h1>&#127859; Chef System</h1>
  <p>One big phone-friendly kitchen screen. See what to cook right now,
     press one button at each step, and the counter knows instantly.</p>
  <?php if ($me !== null && $me['role'] === 'chef'): ?>
    <a class="btn" href="<?= e(url('chef/kitchen.php')) ?>">Open the kitchen screen</a>
  <?php else: ?>
    <a class="btn" href="<?= e(url('chef/login.php')) ?>">Chef login</a>
  <?php endif; ?>
</div>

<h2 class="section-title">What the chef sees after logging in</h2>
<div class="steps">
  <div class="step">
    <div class="step-num">&#128220;</div>
    <strong>Incoming orders</strong>
    <p class="muted small">Big cards with the order number, the student's first name,
      the pickup time, the food items with quantities, and any note
      (for example "less chilli").</p>
  </div>
  <div class="step">
    <div class="step-num">&#9989;</div>
    <strong>Accept &rarr; Preparing &rarr; Ready</strong>
    <p class="muted small">One large button per step. Pressing it changes the status
      everywhere at once &mdash; the student and the staff see it immediately.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128260;</div>
    <strong>Auto-refresh</strong>
    <p class="muted small">The screen reloads itself every few seconds, so new orders
      appear on their own. No pressing F5.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128274;</div>
    <strong>No payment or password info</strong>
    <p class="muted small">The chef never sees prices paid, card details, email
      addresses or passwords &mdash; only what to cook and for whom.</p>
  </div>
</div>

<div class="card" style="margin-top:24px">
  <h2>Chef access</h2>
  <p class="muted">This system is only for the chef. Student and staff passwords
    are refused here with a friendly message.</p>
  <p><a class="btn btn-chef" href="<?= e(url('chef/login.php')) ?>">Chef login</a></p>
  <p class="muted small">Demo chef: <code>chef@canteen.local</code> / <code>Chef@123</code>
    (full list in README.md).</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
