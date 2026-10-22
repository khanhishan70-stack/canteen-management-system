<?php
/**
 * student/order_details.php  -  One order: what was ordered, how it is
 * going, the full history and (when the food is ready) the QR code
 * used to collect it.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('student');

/* ------------------------------------------------------------------
 * Cancel an order (only possible while the kitchen has not started it)
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $order_id = post_int('order_id', 0);
    $action   = post_str('action', 20);

    $own = db_row('SELECT order_id, status FROM orders WHERE order_id = ? AND user_id = ?',
                  [$order_id, $me['user_id']]);

    if ($action === 'cancel' && $own) {
        $result = change_order_status(
            $order_id,
            'Cancelled',
            (int)$me['user_id'],
            'student',
            'Cancelled by the student'
        );
        flash_set($result['ok'] ? 'info' : 'error', $result['message']);
    }

    redirect(url('student/order_details.php?id=' . $order_id));
}

/* ------------------------------------------------------------------
 * Show the order
 * ------------------------------------------------------------------ */
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$order = db_row(
    'SELECT o.order_id, o.status, o.pickup_code, o.pickup_time, o.payment_method,
            o.total_amount, o.notes, o.created_at, o.collected_at,
            p.payment_status, p.transaction_ref, p.paid_at
     FROM orders o
     LEFT JOIN payments p ON p.order_id = o.order_id
     WHERE o.order_id = ? AND o.user_id = ?',
    [$order_id, $me['user_id']]
);

if (!$order) {
    flash_set('error', 'We could not find that order.');
    redirect(url('student/orders.php'));
}

$items   = db_all('SELECT item_name, unit_price, quantity, line_total
                   FROM order_items WHERE order_id = ? ORDER BY order_item_id', [$order_id]);
$history = db_all(
    'SELECT h.to_status, h.note, h.created_at, u.full_name
     FROM order_status_history h
     LEFT JOIN users u ON u.user_id = h.changed_by
     WHERE h.order_id = ?
     ORDER BY h.created_at ASC, h.history_id ASC',
    [$order_id]
);

$can_cancel = in_array('Cancelled', allowed_next_statuses($order['status'], 'student'), true);
$can_pay    = in_array($order['status'], ['Pending Payment', 'Payment Failed'], true)
              && $order['payment_method'] === 'online';
$show_qr    = in_array($order['status'], ['Ready', 'Collected'], true);

$page_title = 'Order #' . $order_id;
require __DIR__ . '/../includes/header.php';
?>

<p><a href="<?= e(url('student/orders.php')) ?>">&larr; Back to my orders</a></p>

<div class="card">
  <h1>Order #<?= (int)$order['order_id'] ?></h1>
  <p>
    <?= status_badge($order['status']) ?>
    <span class="muted small">Placed <?= e(format_datetime($order['created_at'])) ?></span>
  </p>
  <p class="muted"><?= e(order_status_hint($order['status'])) ?></p>

  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th>Item</th><th>Price</th><th>Qty</th><th>Line total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= e($item['item_name']) ?></td>
            <td><?= e(money($item['unit_price'])) ?></td>
            <td><?= (int)$item['quantity'] ?></td>
            <td><?= e(money($item['line_total'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="total-row">
    <span>Total</span>
    <span><?= e(money($order['total_amount'])) ?></span>
  </div>

  <p class="muted small" style="margin-top:10px">
    Pickup time: <strong><?= e(format_time($order['pickup_time'])) ?></strong> &middot;
    Payment:
    <?php if ($order['payment_method'] === 'online'): ?>
      QR scan &mdash; paid from wallet
      <?php if ($order['payment_status'] === 'paid'): ?>
        &mdash; paid <?= e(format_datetime($order['paid_at'])) ?>
        (ref <?= e((string)$order['transaction_ref']) ?>)
      <?php elseif ($order['payment_status'] === 'failed'): ?>
        &mdash; <span style="color:var(--red)">failed</span>
      <?php else: ?>
        &mdash; not confirmed yet
      <?php endif; ?>
    <?php else: ?>
      Cash on Delivery (COD)
      <?php if ($order['payment_status'] === 'paid'): ?>
        &mdash; paid when collected
      <?php else: ?>
        &mdash; <strong>please pay cash at the counter</strong>
      <?php endif; ?>
    <?php endif; ?>
  </p>

  <?php if ($order['notes'] !== null && $order['notes'] !== ''): ?>
    <p class="muted small">Your note: &ldquo;<?= e($order['notes']) ?>&rdquo;</p>
  <?php endif; ?>

  <div class="form-actions">
    <?php if ($can_pay): ?>
      <a class="btn btn-success" href="<?= e(url('student/payment.php?order=' . $order_id)) ?>">
        <?= $order['status'] === 'Payment Failed' ? 'Scan the QR to pay again' : 'Show payment QR code' ?>
      </a>
    <?php endif; ?>

    <?php if ($can_cancel): ?>
      <form method="post" data-confirm="Cancel this order?">
        <?= csrf_field() ?>
        <input type="hidden" name="order_id" value="<?= $order_id ?>">
        <button class="btn btn-danger" type="submit" name="action" value="cancel">Cancel order</button>
      </form>
    <?php endif; ?>

    <a class="btn btn-muted" href="<?= e(url('student/menu.php')) ?>">Order more food</a>
  </div>
</div>

<?php if ($show_qr): ?>
  <!-- The QR picture is drawn by assets/js/qrcode.js (open source, offline). -->
  <script src="<?= e(url('assets/js/qrcode.js')) ?>"></script>

  <div class="qr-card">
    <h2>&#128241; Your pickup QR code</h2>
    <?php if ($order['status'] === 'Ready'): ?>
      <p><span class="badge badge-ready">Show this at the counter</span></p>
    <?php else: ?>
      <p><span class="badge badge-collected">This order was collected</span></p>
    <?php endif; ?>

    <div id="qrcode" class="qr-image" data-code="<?= e($order['pickup_code']) ?>"></div>
    <div class="qr-code"><?= e($order['pickup_code']) ?></div>
    <p class="qr-wait">
      The QR picture contains only this pickup code &mdash; no name, phone number,
      password or payment detail is hidden inside it.
    </p>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Order history</h2>
  <ul class="timeline">
    <?php foreach ($history as $step): ?>
      <li>
        <div class="t-title">
          <?php if ($step['to_status'] === 'Ready'): ?>
            &#127860; Ready for pickup
          <?php else: ?>
            <?= e($step['to_status']) ?>
          <?php endif; ?>
        </div>
        <div class="t-time"><?= e(format_datetime($step['created_at'])) ?></div>
        <?php if ($step['note'] !== null && $step['note'] !== ''): ?>
          <div class="small muted"><?= e($step['note']) ?></div>
        <?php endif; ?>
        <?php if ($step['full_name'] !== null): ?>
          <div class="small muted">by <?= e(short_name($step['full_name'])) ?></div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
