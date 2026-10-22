<?php
/**
 * student/orders.php  -  The order history of the logged in student,
 * with the current (unfinished) order on top.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('student');

$orders = db_all(
    'SELECT o.order_id, o.status, o.pickup_time, o.total_amount, o.payment_method,
            o.created_at, o.collected_at,
            (SELECT SUM(quantity) FROM order_items WHERE order_id = o.order_id) AS items_total,
            p.payment_status
     FROM orders o
     LEFT JOIN payments p ON p.order_id = o.order_id
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC
     LIMIT 50',
    [$me['user_id']]
);

/** Orders that are still moving through the kitchen. */
$active_statuses = ['Pending Payment', 'Payment Failed', 'Paid', 'Sent to Chef', 'Accepted', 'Preparing', 'Ready'];
$current = null;
foreach ($orders as $order) {
    if (in_array($order['status'], $active_statuses, true)) {
        $current = $order;
        break;
    }
}

$page_title = 'My orders';
require __DIR__ . '/../includes/header.php';
?>

<h1>My orders</h1>

<?php if (!$orders): ?>
  <?php empty_state('You have not ordered yet', 'Your orders will appear here as soon as you place one.'); ?>
  <p class="center"><a class="btn btn-primary" href="<?= e(url('student/menu.php')) ?>">Order something tasty</a></p>

<?php else: ?>

  <?php if ($current !== null): ?>
    <div class="card" style="border-left:5px solid var(--orange)">
      <h2>Current order &mdash; #<?= (int)$current['order_id'] ?></h2>
      <p>
        <?= status_badge($current['status']) ?>
        <span class="muted small">
          Pickup <?= e(format_time($current['pickup_time'])) ?> &middot;
          <?= e(money($current['total_amount'])) ?>
        </span>
      </p>
      <p class="muted small"><?= e(order_status_hint($current['status'])) ?></p>
      <a class="btn btn-small btn-primary" href="<?= e(url('student/order_details.php?id=' . (int)$current['order_id'])) ?>">
        View details and QR code
      </a>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Order</th>
            <th>Placed</th>
            <th>Pickup</th>
            <th>Items</th>
            <th>Payment</th>
            <th>Total</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><strong>#<?= (int)$order['order_id'] ?></strong></td>
              <td><?= e(format_datetime($order['created_at'])) ?></td>
              <td><?= e(format_time($order['pickup_time'])) ?></td>
              <td><?= (int)$order['items_total'] ?></td>
              <td>
                <?= e(payment_method_label($order['payment_method'])) ?>
                <?php if ($order['payment_status'] === 'due_at_pickup'): ?>
                  <span class="badge badge-pending">Due at pickup</span>
                <?php elseif ($order['payment_status'] === 'failed'): ?>
                  <span class="badge badge-failed">Failed</span>
                <?php elseif ($order['payment_status'] === 'paid'): ?>
                  <span class="badge badge-paid">Paid</span>
                <?php endif; ?>
              </td>
              <td><?= e(money($order['total_amount'])) ?></td>
              <td><?= status_badge($order['status']) ?></td>
              <td><a class="btn btn-small btn-muted" href="<?= e(url('student/order_details.php?id=' . (int)$order['order_id'])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
