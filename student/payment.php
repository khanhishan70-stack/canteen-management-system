<?php
/**
 * student/payment.php  -  LAPTOP screen: the payment QR code.
 *
 * The student chose "Online payment" on the laptop. This page shows a
 * big QR picture. The student SCANS IT WITH A PHONE, the phone opens
 * pay.php (the phone payment system) and the demo payment is confirmed
 * there. This laptop page refreshes itself, so as soon as the phone
 * presses the button the screen flips to "Paid".
 *
 * The QR picture contains ONLY a link with a secret random token.
 * No name, no card number, no password, no phone number.
 *
 * For testing without a phone, the same link is printed under the QR
 * and can be opened on this computer with the "pay on this device" button.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('student');

/** Load one order that belongs to the logged in student. */
function load_own_order(int $order_id, int $user_id): ?array
{
    return db_row(
        'SELECT o.order_id, o.status, o.total_amount, o.pickup_time, o.pickup_code,
                o.payment_method, p.payment_status, p.payment_token, p.amount
         FROM orders o
         LEFT JOIN payments p ON p.order_id = o.order_id
         WHERE o.order_id = ? AND o.user_id = ?',
        [$order_id, $user_id]
    );
}

$order_id = isset($_GET['order']) ? (int)$_GET['order'] : 0;
$order    = load_own_order($order_id, (int)$me['user_id']);

if (!$order) {
    flash_set('error', 'We could not find that order.');
    redirect(url('student/orders.php'));
}

/* Cash orders never show a payment QR. */
if ($order['payment_method'] !== 'online' || empty($order['payment_token'])) {
    flash_set('info', 'This order is paid at the counter, so no payment QR is needed.');
    redirect(url('student/order_details.php?id=' . $order['order_id']));
}

/*
 * Paid already (for example the phone confirmed it a second ago)?
 * Show the happy screen right here instead of sending the student away.
 */
$paid   = ($order['status'] === 'Paid');
$failed = ($order['status'] === 'Payment Failed');
$waiting = ($order['status'] === 'Pending Payment');

/* Any other status (Sent to Chef, Ready, ...) -> normal order page. */
if (!$paid && !$failed && !$waiting) {
    redirect(url('student/order_details.php?id=' . $order['order_id']));
}

/* The link that hides inside the QR picture (the phone opens it). */
$pay_url   = absolute_url('pay.php?token=' . $order['payment_token']);
$balance   = wallet_balance((int)$me['user_id']);
$amount    = (float)$order['amount'];
$items     = db_all(
    'SELECT item_name, quantity, line_total
     FROM order_items WHERE order_id = ? ORDER BY order_item_id',
    [$order_id]
);
$failed    = ($order['status'] === 'Payment Failed');

$page_title = 'Scan to pay';
require __DIR__ . '/../includes/header.php';
?>

<div class="notice-sim">
  <h3>&#9888; Simulation only &mdash; no real payment</h3>
  <p style="margin:0">
    Scanning this QR opens a <strong>demo</strong> payment page on your phone.
    The amount <?= e(money($amount)) ?> is taken from your
    <strong><?= e(money_format_balance($balance)) ?> demo wallet</strong>.
    No real money is ever charged, and the picture contains no card details.
  </p>
</div>

<div class="wallet-panel">
  <div>
    <div class="wallet-label">&#128176; Your demo wallet</div>
    <div class="wallet-amount <?= $balance >= $amount ? '' : 'wallet-low' ?>">
      <?= e(money_format_balance($balance)) ?>
    </div>
  </div>
  <div class="wallet-label">
    This order needs <?= e(money($amount)) ?>
    <?php if ($balance < $amount): ?>
      &mdash; <b class="wallet-low">not enough balance</b>. Use COD next time.
    <?php endif; ?>
  </div>
</div>

<div class="pay-wait"<?= ($waiting || $failed) ? ' data-autorefresh="4"' : '' ?>>
  <div class="card narrow" style="text-align:center">

    <p class="portal-tag">&#128241; Pay with your phone</p>
    <h1>Order #<?= (int)$order['order_id'] ?></h1>

    <?php if ($paid): ?>
      <div class="pay-result pay-ok">
        <div class="pay-icon">&#9989;</div>
        <h2>Payment received!</h2>
        <p><?= e(money($order['amount'])) ?> &mdash; demo payment recorded on your phone.</p>
        <p class="muted small">No real money was charged. The canteen staff
          can now send your order to the kitchen.</p>
        <div class="form-actions" style="justify-content:center">
          <a class="btn btn-primary" href="<?= e(url('student/order_details.php?id=' . (int)$order['order_id'])) ?>">Track my order</a>
        </div>
      </div>

    <?php else: ?>
      <?php if ($failed): ?>
        <div class="alert alert-error">
          The last demo payment did not go through. Scan the QR again to retry.
        </div>
      <?php else: ?>
        <p class="muted">Scan this QR code with your <strong>phone camera</strong>,
          then press <strong>Confirm demo payment</strong> on the phone.
          This page updates itself automatically.</p>
      <?php endif; ?>
      <!-- The QR picture is drawn in the browser by qrcode.js -->
      <div id="qrcode" class="qr-image qr-image-big" data-code="<?= e($pay_url) ?>"></div>

      <p class="muted small" style="word-break:break-all">
        Or open this link directly:<br>
        <code><?= e($pay_url) ?></code>
      </p>

      <div class="form-actions" style="justify-content:center">
        <a class="btn btn-success" href="<?= e($pay_url) ?>">Pay on this device (testing)</a>
        <a class="btn btn-muted" href="<?= e(url('student/order_details.php?id=' . (int)$order['order_id'])) ?>">Back to order</a>
      </div>

      <p class="muted small" style="margin-top:10px">
        Got the QR on another screen? Open <strong>&#128247; Scan &amp; pay</strong>
        on your phone (top menu) and point its camera at this code.
      </p>

      <p class="muted small">&#8635; This screen refreshes every few seconds &mdash;
        as soon as the phone confirms, it turns into &ldquo;Paid&rdquo;.</p>

      <div class="table-wrap" style="text-align:left;margin-top:14px">
        <table class="data">
          <thead><tr><th>Item</th><th>Qty</th><th>Total</th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= e($item['item_name']) ?></td>
                <td><?= (int)$item['quantity'] ?></td>
                <td><?= e(money($item['line_total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="total-row">
        <span>Amount</span>
        <span><?= e(money($order['amount'])) ?></span>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
