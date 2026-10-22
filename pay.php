<?php
/**
 * pay.php  -  PHONE PAYMENT SYSTEM (the 4th system).
 *
 * HOW IT WORKS
 *   1. The student orders on the laptop and chooses "Online payment".
 *   2. The laptop shows a QR picture. Inside that picture is a link
 *      to THIS page, e.g.  pay.php?token=8f3a91c2...
 *   3. The student scans the QR with a PHONE. The phone opens this
 *      page and shows the order + amount.
 *   4. The student presses "Confirm demo payment" ON THE PHONE.
 *   5. The order becomes "Paid" and the laptop screen updates itself.
 *
 * IMPORTANT - READ THIS:
 *   This page does NOT connect to any bank or payment company.
 *   No card number is asked for, and no real money is charged.
 *   Pressing the button simply records "paid" inside this database,
 *   exactly like a real payment gateway callback would do.
 *
 * SECURITY
 *   - No login is needed (just like a real payment page), but the
 *     long random token in the link can only be created by the system.
 *   - The token is checked against the database, so a made-up link
 *     shows a friendly "invalid" page and nothing else.
 *   - This page shows NO student name, NO phone number, NO pickup
 *     code and NO password - only the order number and the food.
 *   - The same CSRF + workflow rules of the rest of the project apply.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

/** Find an order by its secret payment token (never guessable). */
function find_order_by_payment_token(string $token): ?array
{
    if ($token === '' || strlen($token) !== 32) {
        return null;
    }
    return db_row(
        'SELECT o.order_id, o.status, o.total_amount, o.payment_method, o.user_id,
                p.payment_id, p.payment_status, p.payment_token, p.amount
         FROM payments p
         JOIN orders o ON o.order_id = p.order_id
         WHERE p.payment_token = ?',
        [$token]
    );
}

$token  = isset($_GET['token']) ? preg_replace('/[^a-f0-9]/i', '', (string)$_GET['token']) : '';
$pay    = $token !== '' ? find_order_by_payment_token($token) : null;
$message = null;

/* ------------------------------------------------------------------
 * The phone pressed a button: Confirm payment  /  Simulate failure
 * ------------------------------------------------------------------ */
if ($pay !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_str('action', 10);

    if ($action === 'pay') {
        // The button was pressed, so (and only now) the simulated
        // payment succeeds. The order's own student is recorded as
        // the owner, exactly like a payment gateway callback.
        $result = change_order_status(
            (int)$pay['order_id'],
            'Paid',
            null,
            'student',
            'Demo payment confirmed by scanning the QR code (phone)'
        );
        $message = ['type' => $result['ok'] ? 'success' : 'error', 'text' => $result['message']];
    }

    if ($action === 'fail') {
        // The demo card was "declined" - useful for showing the
        // Payment Failed status during the presentation.
        $result = change_order_status(
            (int)$pay['order_id'],
            'Payment Failed',
            null,
            'student',
            'Simulated card decline (demo, phone)'
        );
        $message = ['type' => 'error', 'text' => $result['message']];
    }

    // Reload the fresh status (PRG pattern - never re-post on refresh).
    $pay = find_order_by_payment_token($token);
}

/* Bad or missing token: show a friendly page and nothing else. */
if ($pay === null) {
    $page_title = 'Demo payment';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="auth-page">
      <div class="card">
        <h1>&#128274; Invalid payment link</h1>
        <p class="muted">This payment link is not valid or has already been used.
          Please ask at the counter, or place the order again.</p>
        <p><a class="btn btn-muted" href="<?= e(url('index.php')) ?>">Back to the home page</a></p>
      </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$items = db_all(
    'SELECT item_name, unit_price, quantity, line_total
     FROM order_items WHERE order_id = ? ORDER BY order_item_id',
    [(int)$pay['order_id']]
);

$paid  = ($pay['status'] === 'Paid');
$failed = ($pay['status'] === 'Payment Failed');
$closed = !in_array($pay['status'], ['Pending Payment', 'Payment Failed'], true);

$balance   = wallet_balance((int)$pay['user_id']);
$payable   = (float)$pay['amount'];
$has_money = ($balance >= $payable);

$page_title = 'Demo payment';
require __DIR__ . '/includes/header.php';
?>

<div class="notice-sim">
  <h3>&#9888; Simulation only &mdash; no real payment</h3>
  <p style="margin:0">
    This is a diploma project. This page is <strong>not</strong> connected to any bank or card
    company, it asks for <strong>no card details</strong>, and <strong>no real money is charged</strong>.
    Pressing the button below takes <?= e(money($payable)) ?> out of the student's
    <strong>demo wallet</strong>.
  </p>
</div>

<?php if (!$paid && !$closed && $has_money): ?>
  <div class="wallet-panel">
    <div>
      <div class="wallet-label">&#128176; Student demo wallet</div>
      <div class="wallet-amount"><?= e(money_format_balance($balance)) ?></div>
    </div>
    <div class="wallet-label">After paying: <?= e(money_format_balance($balance - $payable)) ?></div>
  </div>
<?php endif; ?>

<div class="auth-page portal-student" data-pay-status="<?= e($pay['status']) ?>">
  <div class="card">
    <p class="portal-tag">&#128241; Phone payment</p>
    <h1>Order #<?= (int)$pay['order_id'] ?></h1>

    <?php if ($message !== null): ?>
      <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['text']) ?></div>
    <?php endif; ?>

    <?php if ($paid): ?>
      <div class="pay-result pay-ok">
        <div class="pay-icon">&#9989;</div>
        <h2>Payment successful</h2>
        <p><?= e(money($pay['amount'])) ?> &mdash; demo transaction recorded.</p>
        <p class="muted small">No real money was charged. You can close this page
          and collect your food at the pickup time.</p>
      </div>

    <?php elseif ($closed && !$failed): ?>
      <div class="pay-result">
        <h2>This order is <?= e($pay['status']) ?></h2>
        <p class="muted">No payment is needed on this page any more.</p>
      </div>

    <?php else: ?>
      <?php if ($failed): ?>
        <div class="pay-result pay-bad">
          <div class="pay-icon">&#10060;</div>
          <h2>Payment failed</h2>
          <p>The demo payment did not go through. You can try again below,
            or pay cash at the counter when you collect.</p>
        </div>
      <?php endif; ?>

      <div class="table-wrap">
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
        <span>Amount to pay</span>
        <span><?= e(money($pay['amount'])) ?></span>
      </div>

      <?php if (!$has_money): ?>
        <div class="alert alert-error">
          This wallet only has <?= e(money_format_balance($balance)) ?>, so the QR payment
          cannot go through. Ask the student to place the order again as
          <strong>Cash on Delivery (COD)</strong>, or add demo money on the laptop
          checkout page and scan again.
        </div>
      <?php else: ?>
      <form method="post" data-submit-once>
        <?= csrf_field() ?>

        <div class="form-actions" style="flex-direction:column">
          <button class="btn btn-success btn-block" type="submit" name="action" value="pay">
            <?= $failed ? 'Try the wallet payment again' : 'Confirm payment from wallet' ?>
          </button>
          <?php if (!$failed): ?>
            <button class="btn btn-muted btn-block" type="submit" name="action" value="fail">
              Simulate a failed payment
            </button>
          <?php endif; ?>
        </div>
      </form>
      <?php endif; ?>
    <?php endif; ?>

    <p class="auth-links muted small">
      Campus Canteen &mdash; Diploma DBMS project. Payment is a simulation.
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
