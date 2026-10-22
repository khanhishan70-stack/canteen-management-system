<?php
/**
 * manager/order_details.php  -  Everything about one order:
 * the student, the items, the payment and the complete history.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('manager');

/* ------------------------------------------------------------------
 * Status changes from this page
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $order_id = post_int('order_id', 0);
    $action   = post_str('action', 20);

    $map = [
        'send'   => ['Sent to Chef', 'Sent to the kitchen by the manager'],
        'cancel' => ['Cancelled',    'Cancelled by the manager'],
    ];

    if (isset($map[$action])) {
        $result = change_order_status(
            $order_id,
            $map[$action][0],
            (int)$me['user_id'],
            'manager',
            $map[$action][1]
        );
        flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    }

    redirect(url('manager/order_details.php?id=' . $order_id));
}

/* ------------------------------------------------------------------
 * Read the order
 * ------------------------------------------------------------------ */
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$order = db_row(
    'SELECT o.order_id, o.status, o.pickup_code, o.pickup_time, o.payment_method,
            o.total_amount, o.notes, o.created_at, o.collected_at,
            u.full_name, u.email, u.phone,
            p.payment_status, p.transaction_ref, p.paid_at
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN payments p ON p.order_id = o.order_id
     WHERE o.order_id = ?',
    [$order_id]
);

if (!$order) {
    flash_set('error', 'Order not found.');
    redirect(url('manager/dashboard.php'));
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

$next_statuses = allowed_next_statuses($order['status'], 'manager');

$page_title = 'Order #' . $order_id;
require __DIR__ . '/../includes/header.php';
?>

<p><a href="<?= e(url('manager/dashboard.php')) ?>">&larr; Back to the dashboard</a></p>

<div class="card">
  <h1>Order #<?= (int)$order['order_id'] ?> <?= status_badge($order['status']) ?></h1>
  <p class="muted">
    Placed <?= e(format_datetime($order['created_at'])) ?> &middot;
    pickup at <strong><?= e(format_time($order['pickup_time'])) ?></strong>
  </p>

  <div class="form-row two">
    <div>
      <h2>Student</h2>
      <p><?= e($order['full_name']) ?><br>
         <span class="muted small"><?= e($order['email']) ?></span><br>
         <span class="muted small"><?= e($order['phone'] ?: 'no phone') ?></span>
      </p>
    </div>
    <div>
      <h2>Payment</h2>
      <p>
        Method: <strong><?= e(payment_method_label($order['payment_method'])) ?></strong><br>
        Status:
        <?php if ($order['payment_status'] === 'paid'): ?>
          <span class="badge badge-paid">Paid</span>
          <span class="small muted"><?= e(format_datetime($order['paid_at'])) ?><?= $order['transaction_ref'] ? ' &middot; ' . e($order['transaction_ref']) : '' ?></span>
        <?php elseif ($order['payment_status'] === 'failed'): ?>
          <span class="badge badge-failed">Failed</span>
        <?php elseif ($order['payment_status'] === 'due_at_pickup'): ?>
          <span class="badge badge-pending">Due at pickup</span>
        <?php else: ?>
          <span class="badge badge-pending">Waiting for demo payment</span>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Item</th><th>Price at order time</th><th>Qty</th><th>Line total</th></tr></thead>
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

  <div class="total-row"><span>Total</span><span><?= e(money($order['total_amount'])) ?></span></div>

  <?php if ($order['notes'] !== null && $order['notes'] !== ''): ?>
    <p class="muted small" style="margin-top:10px">Note from the student: &ldquo;<?= e($order['notes']) ?>&rdquo;</p>
  <?php endif; ?>

  <div class="form-actions">
    <?php if (in_array('Sent to Chef', $next_statuses, true)): ?>
      <form method="post" class="inline-form" data-submit-once>
        <?= csrf_field() ?>
        <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
        <button class="btn btn-info" type="submit" name="action" value="send">Send to chef</button>
      </form>
    <?php endif; ?>

    <?php if ($order['status'] === 'Ready'): ?>
      <a class="btn btn-success" href="<?= e(url('manager/verify.php?code=' . urlencode($order['pickup_code']))) ?>">
        Verify QR &amp; collect
      </a>
    <?php endif; ?>

    <?php if (in_array('Cancelled', $next_statuses, true)): ?>
      <form method="post" class="inline-form" data-confirm="Cancel order #<?= (int)$order['order_id'] ?>?">
        <?= csrf_field() ?>
        <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
        <button class="btn btn-danger" type="submit" name="action" value="cancel">Cancel order</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Status history</h2>
  <ul class="timeline">
    <?php foreach ($history as $step): ?>
      <li>
        <div class="t-title"><?= e($step['to_status']) ?></div>
        <div class="t-time"><?= e(format_datetime($step['created_at'])) ?></div>
        <?php if ($step['note'] !== null && $step['note'] !== ''): ?>
          <div class="small muted"><?= e($step['note']) ?></div>
        <?php endif; ?>
        <?php if ($step['full_name'] !== null): ?>
          <div class="small muted">by <?= e($step['full_name']) ?></div>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
