<?php
/**
 * manager/dashboard.php  -  The manager's home page.
 * Shows today's numbers, the list of orders and the buttons that send
 * a paid (or cash) order to the chef.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('manager');

/* ------------------------------------------------------------------
 * Status changes started from this page (Send to chef / Cancel)
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

    $back = isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : '';
    redirect(url('manager/dashboard.php') . $back);
}

/* ------------------------------------------------------------------
 * Which orders should be listed? (tabs on the page)
 * ------------------------------------------------------------------ */
$tabs = [
    'active'  => ['Pending Payment', 'Payment Failed', 'Paid', 'Sent to Chef', 'Accepted', 'Preparing', 'Ready'],
    'tosend'  => ['Pending Payment', 'Payment Failed', 'Paid'],
    'kitchen' => ['Sent to Chef', 'Accepted', 'Preparing'],
    'ready'   => ['Ready'],
    'done'    => ['Collected', 'Cancelled'],
    'all'     => order_statuses(),
];
$filter = isset($_GET['filter']) && isset($tabs[$_GET['filter']]) ? $_GET['filter'] : 'active';

$in        = implode(',', array_fill(0, count($tabs[$filter]), '?'));
$orders    = db_all(
    "SELECT o.order_id, o.status, o.pickup_time, o.total_amount, o.payment_method,
            o.created_at, o.collected_at, o.notes,
            u.full_name, p.payment_status,
            (SELECT SUM(quantity) FROM order_items WHERE order_id = o.order_id) AS items_total
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN payments p ON p.order_id = o.order_id
     WHERE o.status IN ($in)
     ORDER BY o.created_at DESC
     LIMIT 100",
    $tabs[$filter]
);

/* ------------------------------------------------------------------
 * Numbers for today
 * ------------------------------------------------------------------ */
$stats = db_row(
    "SELECT COUNT(*) AS today_orders,
            COALESCE(SUM(CASE WHEN status NOT IN ('Pending Payment','Payment Failed','Cancelled')
                              THEN total_amount END), 0) AS today_sales,
            COALESCE(SUM(status = 'Paid'), 0) AS waiting_send,
            COALESCE(SUM(status IN ('Sent to Chef','Accepted','Preparing')), 0) AS in_kitchen,
            COALESCE(SUM(status = 'Ready'), 0) AS ready_now
     FROM orders WHERE DATE(created_at) = CURDATE()"
);

$page_title = 'Staff dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1>Staff dashboard</h1>

<div class="stat-grid">
  <div class="stat">
    <span class="stat-value"><?= (int)$stats['today_orders'] ?></span>
    <span class="stat-label">Orders today</span>
  </div>
  <div class="stat green">
    <span class="stat-value"><?= e(money($stats['today_sales'])) ?></span>
    <span class="stat-label">Sales today</span>
  </div>
  <div class="stat blue">
    <span class="stat-value"><?= (int)$stats['waiting_send'] ?></span>
    <span class="stat-label">Waiting to send</span>
  </div>
  <div class="stat">
    <span class="stat-value"><?= (int)$stats['in_kitchen'] ?></span>
    <span class="stat-label">In the kitchen</span>
  </div>
  <div class="stat green">
    <span class="stat-value"><?= (int)$stats['ready_now'] ?></span>
    <span class="stat-label">Ready for pickup</span>
  </div>
</div>

<div class="cat-tabs" style="margin-top:20px">
  <?php
    $labels = [
        'active' => 'In progress', 'tosend' => 'To send', 'kitchen' => 'In kitchen',
        'ready' => 'Ready', 'done' => 'Finished', 'all' => 'Everything',
    ];
  ?>
  <?php foreach ($labels as $key => $label): ?>
    <a href="<?= e(url('manager/dashboard.php?filter=' . $key)) ?>"
       class="<?= $filter === $key ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$orders): ?>
  <?php empty_state('No orders in this list', 'New orders will appear here by themselves.'); ?>

<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Order</th>
            <th>Time</th>
            <th>Student</th>
            <th>Items</th>
            <th>Payment</th>
            <th>Total</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><strong>#<?= (int)$order['order_id'] ?></strong></td>
              <td>
                <?= e(format_time($order['pickup_time'])) ?>
                <div class="small muted"><?= e(date('h:i A', strtotime($order['created_at']))) ?></div>
              </td>
              <td><?= e(short_name($order['full_name'])) ?></td>
              <td><?= (int)$order['items_total'] ?></td>
              <td>
                <?= e(payment_method_label($order['payment_method'])) ?>
                <?php if ($order['payment_status'] === 'paid'): ?>
                  <span class="badge badge-paid">Paid</span>
                <?php elseif ($order['payment_status'] === 'failed'): ?>
                  <span class="badge badge-failed">Failed</span>
                <?php elseif ($order['payment_status'] === 'due_at_pickup'): ?>
                  <span class="badge badge-pending">Due</span>
                <?php else: ?>
                  <span class="badge badge-pending">Waiting</span>
                <?php endif; ?>
              </td>
              <td><?= e(money($order['total_amount'])) ?></td>
              <td><?= status_badge($order['status']) ?></td>
              <td class="nowrap">
                <a class="btn btn-small btn-muted" href="<?= e(url('manager/order_details.php?id=' . (int)$order['order_id'])) ?>">View</a>

                <?php if (in_array('Sent to Chef', allowed_next_statuses($order['status'], 'manager'), true)): ?>
                  <form method="post" class="inline-form" data-submit-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                    <button class="btn btn-small btn-info" type="submit" name="action" value="send">Send to chef</button>
                  </form>
                <?php endif; ?>

                <?php if ($order['status'] === 'Ready'): ?>
                  <a class="btn btn-small btn-success" href="<?= e(url('manager/verify.php?code=' . urlencode($order['pickup_code']))) ?>">Collect</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
