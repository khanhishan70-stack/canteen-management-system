<?php
/**
 * manager/reports.php  -  Simple daily reports.
 * Only SQL GROUP BY queries - a good example of what the database
 * can do with the data the canteen collects every day.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('manager');

/* ------------------------------------------------------------------
 * 1. Today at a glance
 * ------------------------------------------------------------------ */
$today = db_row(
    "SELECT COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN status NOT IN ('Pending Payment','Payment Failed','Cancelled')
                              THEN total_amount END), 0) AS sales,
            COALESCE(SUM(payment_method = 'online'), 0) AS online_orders,
            COALESCE(SUM(payment_method = 'cash'), 0) AS cash_orders,
            COALESCE(SUM(status = 'Collected'), 0) AS collected,
            COALESCE(SUM(status = 'Cancelled'), 0) AS cancelled
     FROM orders WHERE DATE(created_at) = CURDATE()"
);

/* ------------------------------------------------------------------
 * 2. Last seven days, one line per day
 * ------------------------------------------------------------------ */
$days = db_all(
    "SELECT DATE(created_at) AS day,
            COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN status NOT IN ('Pending Payment','Payment Failed','Cancelled')
                              THEN total_amount END), 0) AS sales
     FROM orders
     WHERE created_at >= CURDATE() - INTERVAL 6 DAY
     GROUP BY DATE(created_at)
     ORDER BY day DESC"
);

/* ------------------------------------------------------------------
 * 3. Best selling food
 * ------------------------------------------------------------------ */
$top = db_all(
    "SELECT oi.item_name,
            SUM(oi.quantity) AS qty,
            SUM(oi.line_total) AS revenue
     FROM order_items oi
     JOIN orders o ON o.order_id = oi.order_id
     WHERE o.status <> 'Cancelled'
     GROUP BY oi.item_name
     ORDER BY qty DESC
     LIMIT 8"
);

$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>

<h1>Reports</h1>
<p class="muted">Figures are calculated directly by MySQL from the orders table.</p>

<div class="stat-grid">
  <div class="stat">
    <span class="stat-value"><?= (int)$today['orders'] ?></span>
    <span class="stat-label">Orders today</span>
  </div>
  <div class="stat green">
    <span class="stat-value"><?= e(money($today['sales'])) ?></span>
    <span class="stat-label">Sales today</span>
  </div>
  <div class="stat blue">
    <span class="stat-value"><?= (int)$today['online_orders'] ?></span>
    <span class="stat-label">QR scan orders</span>
  </div>
  <div class="stat">
    <span class="stat-value"><?= (int)$today['cash_orders'] ?></span>
    <span class="stat-label">COD orders</span>
  </div>
  <div class="stat green">
    <span class="stat-value"><?= (int)$today['collected'] ?></span>
    <span class="stat-label">Collected today</span>
  </div>
  <div class="stat red">
    <span class="stat-value"><?= (int)$today['cancelled'] ?></span>
    <span class="stat-label">Cancelled today</span>
  </div>
</div>

<h2 class="section-title">Last 7 days</h2>
<?php if (!$days): ?>
  <?php empty_state('No orders in the last week', 'Figures appear here once students start ordering.'); ?>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Day</th><th>Orders</th><th>Sales</th></tr></thead>
        <tbody>
          <?php foreach ($days as $day): ?>
            <tr>
              <td><?= e(date('d M Y', strtotime($day['day']))) ?></td>
              <td><?= (int)$day['orders'] ?></td>
              <td><?= e(money($day['sales'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<h2 class="section-title">Best selling items</h2>
<?php if (!$top): ?>
  <?php empty_state('Nothing sold yet', 'The most popular food will be listed here.'); ?>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Item</th><th>Quantity sold</th><th>Money earned</th></tr></thead>
        <tbody>
          <?php foreach ($top as $row): ?>
            <tr>
              <td><?= e($row['item_name']) ?></td>
              <td><?= (int)$row['qty'] ?></td>
              <td><?= e(money($row['revenue'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
