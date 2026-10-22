<?php
/**
 * student/index.php  -  STUDENT SYSTEM landing page.
 * This is the separate live link for students:
 *   .../student/
 * It explains what a student can do here, then offers
 * the student login and registration.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = current_user();
$page_title = 'Student System';
require __DIR__ . '/../includes/header.php';
?>

<div class="hero">
  <h1>&#127860; Student System</h1>
  <p>Order your food from your laptop before the bell &mdash; <strong>no login needed</strong>.
     You start with <strong>Rs. 500.00 demo money</strong>, pay by scanning a QR code
     (or Cash on Delivery), then collect it with another QR code.</p>
  <a class="btn" href="<?= e(url('student/menu.php')) ?>">Open the menu</a>
  <?php if ($me !== null && $me['role'] === 'student'): ?>
    <a class="btn" href="<?= e(url('student/orders.php')) ?>">Track my orders</a>
  <?php else: ?>
    <a class="btn" href="<?= e(url('register.php')) ?>">Register (optional)</a>
  <?php endif; ?>
</div>

<h2 class="section-title">What you can do here</h2>
<div class="steps">
  <div class="step">
    <div class="step-num">&#127860;</div>
    <strong>Browse the menu</strong>
    <p class="muted small">See today's food by category with prices, and add items to your cart.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128722;</div>
    <strong>Cart &amp; pickup time</strong>
    <p class="muted small">Change quantities, then choose when you will collect the food.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128176;</div>
    <strong>Rs. 500 demo wallet</strong>
    <p class="muted small">Every student account starts with Rs. 500.00 of free demo money.
      Your balance is always shown in the top bar, and you can add more from the checkout page.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128241;</div>
    <strong>Pay by QR scan</strong>
    <p class="muted small">Your laptop shows a payment QR code. Scan it with your
      <strong>phone</strong> and confirm there &mdash; the money leaves your demo wallet and the
      laptop updates by itself. (No real money is ever charged.)</p>
  </div>
  <div class="step">
    <div class="step-num">&#128247;</div>
    <strong>Camera inside &mdash; Scan &amp; pay</strong>
    <p class="muted small">No phone camera app needed: open <strong>Scan &amp; pay</strong>
      in the menu, the camera starts here in the browser, point it at the payment QR
      and the payment page opens by itself.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128260;</div>
    <strong>Or pay Cash on Delivery</strong>
    <p class="muted small">Choose <strong>COD</strong> and pay cash at the counter when you collect.
      Your wallet stays untouched.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128337;</div>
    <strong>Track your order</strong>
    <p class="muted small">Watch it move: Paid &rarr; Sent to Chef &rarr; Preparing &rarr; Ready.</p>
  </div>
  <div class="step">
    <div class="step-num">&#9989;</div>
    <strong>Collect with QR</strong>
    <p class="muted small">When it is Ready you get a pickup QR code. Show it at the counter.</p>
  </div>
</div>

<div class="card" style="margin-top:24px">
  <h2>Ready to order?</h2>
  <p class="muted">Just open the menu &mdash; <strong>there is nothing to log into</strong>.
    Your Rs. 500 demo wallet and your cart are waiting for you.</p>
  <p>
    <a class="btn btn-primary" href="<?= e(url('student/menu.php')) ?>">Open the menu</a>
    <a class="btn btn-muted" href="<?= e(url('register.php')) ?>">Register (optional)</a>
  </p>
  <p class="muted small">Register only if you want your own named account. Ordering,
    paying and tracking all work without it.</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
