<?php
/**
 * staff/index.php  -  STAFF SYSTEM landing page.
 * This is the separate live link for the canteen staff:
 *   .../staff/
 * It explains exactly what the staff member will SEE after logging in,
 * then offers the staff login.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = current_user();
$page_title = 'Staff System';
require __DIR__ . '/../includes/header.php';
?>

<div class="hero">
  <h1>&#128188; Staff System</h1>
  <p>The canteen counter screen. See every order students place, check if they paid,
     send orders to the kitchen and hand over the food.</p>
  <?php if ($me !== null && $me['role'] === 'manager'): ?>
    <a class="btn" href="<?= e(url('manager/dashboard.php')) ?>">Open the order board</a>
  <?php else: ?>
    <a class="btn" href="<?= e(url('staff/login.php')) ?>">Staff login</a>
  <?php endif; ?>
</div>

<h2 class="section-title">What the staff sees after logging in</h2>
<div class="steps">
  <div class="step">
    <div class="step-num">&#128203;</div>
    <strong>Every order, live</strong>
    <p class="muted small">A dashboard with all student orders, grouped by status:
      waiting to send, in the kitchen, ready to collect and collected.
      Names, items, totals, pickup times and payment state.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128176;</div>
    <strong>Who paid, who did not</strong>
    <p class="muted small">Each order shows if the QR demo payment succeeded,
      if it failed, or if the cash must be collected at the counter.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128293;</div>
    <strong>Send to the chef</strong>
    <p class="muted small">One button sends a paid (or cash) order to the kitchen screen.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128241;</div>
    <strong>Verify the pickup QR</strong>
    <p class="muted small">Type the code the student's QR shows, confirm the hand-over.
      An order can never be collected twice.</p>
  </div>
  <div class="step">
    <div class="step-num">&#127860;</div>
    <strong>Manage the menu</strong>
    <p class="muted small">Add, edit or disable food items. Disabled items disappear
      from the student menu instantly but stay inside old orders.</p>
  </div>
  <div class="step">
    <div class="step-num">&#128202;</div>
    <strong>Daily reports</strong>
    <p class="muted small">Orders of the day, total sales, cash vs online split,
      last 7 days and the best selling items.</p>
  </div>
</div>

<div class="card" style="margin-top:24px">
  <h2>Staff access</h2>
  <p class="muted">This system is only for canteen staff. Students and the chef
    cannot log in here &mdash; their passwords are refused with a friendly message.</p>
  <p><a class="btn btn-staff" href="<?= e(url('staff/login.php')) ?>">Staff login</a></p>
  <p class="muted small">Demo staff: <code>manager@canteen.local</code> / <code>Manager@123</code>
    (full list in README.md).</p>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
