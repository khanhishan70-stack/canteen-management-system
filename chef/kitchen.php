<?php
/**
 * chef/kitchen.php  -  The kitchen screen.
 *
 * Designed for a phone: big text, one card per order, one clear
 * button per step. The chef only sees what is needed for cooking:
 * order number, pickup time, student first name and the food.
 * No passwords and no payment details are ever selected here.
 *
 * The page refreshes itself every 15 seconds (see data-autorefresh).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('chef');

/* ------------------------------------------------------------------
 * Chef buttons: Accept -> Start preparing -> Mark ready
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $order_id = post_int('order_id', 0);
    $action   = post_str('action', 20);

    $map = [
        'accept'    => ['Accepted',   'Chef accepted the order'],
        'preparing' => ['Preparing',  'Cooking started'],
        'ready'     => ['Ready',      'Food is ready for pickup'],
    ];

    if (isset($map[$action])) {
        $result = change_order_status(
            $order_id,
            $map[$action][0],
            (int)$me['user_id'],
            'chef',
            $map[$action][1]
        );
        flash_set($result['ok'] ? 'success' : 'error', $result['message']);
    }

    redirect(url('chef/kitchen.php'));
}

/* ------------------------------------------------------------------
 * Orders that are in the kitchen (oldest first - first in, first out)
 * ------------------------------------------------------------------ */
$orders = db_all(
    'SELECT o.order_id, o.status, o.pickup_time, o.notes, o.created_at, u.full_name
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     WHERE o.status IN ("Sent to Chef", "Accepted", "Preparing", "Ready")
     ORDER BY o.created_at ASC'
);

/* All items of those orders in one query, then grouped here. */
$items_by_order = [];
if ($orders) {
    $ids = array_map(function ($o) { return (int)$o['order_id']; }, $orders);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT order_id, item_name, quantity FROM order_items WHERE order_id IN ($in) ORDER BY order_item_id", $ids) as $row) {
        $items_by_order[(int)$row['order_id']][] = $row;
    }
}

/** Split the orders into the four columns of the kitchen board. */
function group_by_status(array $orders, string $status): array
{
    return array_values(array_filter($orders, function ($order) use ($status) {
        return $order['status'] === $status;
    }));
}

$page_title = 'Kitchen screen';
require __DIR__ . '/../includes/header.php';

$body_class = 'kitchen';
?>

<div data-autorefresh="15">

  <h1>Kitchen screen</h1>
  <p class="muted">This screen refreshes by itself every 15 seconds.</p>

  <?php if (!$orders): ?>
    <?php empty_state('The kitchen is clear', 'New orders will appear here as soon as the manager sends them.'); ?>

  <?php else: ?>

    <!-- ---------------- 1. NEW ORDERS ---------------- -->
    <section class="kitchen-section">
      <h2>&#128227; New orders (<?= count(group_by_status($orders, 'Sent to Chef')) ?>)</h2>
      <?php $list = group_by_status($orders, 'Sent to Chef'); ?>
      <?php if (!$list): ?>
        <p class="muted">No new orders waiting.</p>
      <?php endif; ?>
      <div class="kitchen-grid">
        <?php foreach ($list as $order): $oid = (int)$order['order_id']; ?>
          <div class="kitchen-card">
            <div class="kitchen-head">
              <span class="kitchen-num">#<?= $oid ?></span>
              <span class="kitchen-time"><?= e(format_time($order['pickup_time'])) ?></span>
            </div>
            <p class="kitchen-cust"><?= e(short_name($order['full_name'])) ?>
              &middot; ordered <?= e(date('h:i A', strtotime($order['created_at']))) ?></p>
            <ul class="kitchen-items">
              <?php foreach ($items_by_order[$oid] ?? [] as $line): ?>
                <li><span><?= e($line['item_name']) ?></span> <b>x<?= (int)$line['quantity'] ?></b></li>
              <?php endforeach; ?>
            </ul>
            <?php if ($order['notes']): ?>
              <p class="kitchen-cust">&#128172; <?= e($order['notes']) ?></p>
            <?php endif; ?>
            <form method="post" data-submit-once>
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= $oid ?>">
              <button class="btn btn-info" type="submit" name="action" value="accept">&#9989; Accept order</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ---------------- 2. ACCEPTED ---------------- -->
    <section class="kitchen-section">
      <h2>&#128736; Accepted (<?= count(group_by_status($orders, 'Accepted')) ?>)</h2>
      <?php $list = group_by_status($orders, 'Accepted'); ?>
      <?php if (!$list): ?>
        <p class="muted">No accepted orders.</p>
      <?php endif; ?>
      <div class="kitchen-grid">
        <?php foreach ($list as $order): $oid = (int)$order['order_id']; ?>
          <div class="kitchen-card k-accepted">
            <div class="kitchen-head">
              <span class="kitchen-num">#<?= $oid ?></span>
              <span class="kitchen-time"><?= e(format_time($order['pickup_time'])) ?></span>
            </div>
            <p class="kitchen-cust"><?= e(short_name($order['full_name'])) ?></p>
            <ul class="kitchen-items">
              <?php foreach ($items_by_order[$oid] ?? [] as $line): ?>
                <li><span><?= e($line['item_name']) ?></span> <b>x<?= (int)$line['quantity'] ?></b></li>
              <?php endforeach; ?>
            </ul>
            <form method="post" data-submit-once>
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= $oid ?>">
              <button class="btn btn-primary" type="submit" name="action" value="preparing">&#128293; Start preparing</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ---------------- 3. PREPARING ---------------- -->
    <section class="kitchen-section">
      <h2>&#128293; Preparing (<?= count(group_by_status($orders, 'Preparing')) ?>)</h2>
      <?php $list = group_by_status($orders, 'Preparing'); ?>
      <?php if (!$list): ?>
        <p class="muted">Nothing is cooking right now.</p>
      <?php endif; ?>
      <div class="kitchen-grid">
        <?php foreach ($list as $order): $oid = (int)$order['order_id']; ?>
          <div class="kitchen-card k-preparing">
            <div class="kitchen-head">
              <span class="kitchen-num">#<?= $oid ?></span>
              <span class="kitchen-time"><?= e(format_time($order['pickup_time'])) ?></span>
            </div>
            <p class="kitchen-cust"><?= e(short_name($order['full_name'])) ?></p>
            <ul class="kitchen-items">
              <?php foreach ($items_by_order[$oid] ?? [] as $line): ?>
                <li><span><?= e($line['item_name']) ?></span> <b>x<?= (int)$line['quantity'] ?></b></li>
              <?php endforeach; ?>
            </ul>
            <form method="post" data-submit-once>
              <?= csrf_field() ?>
              <input type="hidden" name="order_id" value="<?= $oid ?>">
              <button class="btn btn-success" type="submit" name="action" value="ready">&#10004;&#65039; Mark as Ready</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ---------------- 4. READY ---------------- -->
    <section class="kitchen-section">
      <h2>&#127860; Ready for pickup (<?= count(group_by_status($orders, 'Ready')) ?>)</h2>
      <?php $list = group_by_status($orders, 'Ready'); ?>
      <?php if (!$list): ?>
        <p class="muted">Nothing is waiting at the counter.</p>
      <?php endif; ?>
      <div class="kitchen-grid">
        <?php foreach ($list as $order): $oid = (int)$order['order_id']; ?>
          <div class="kitchen-card k-ready">
            <div class="kitchen-head">
              <span class="kitchen-num">#<?= $oid ?></span>
              <span class="kitchen-time"><?= e(format_time($order['pickup_time'])) ?></span>
            </div>
            <p class="kitchen-cust"><?= e(short_name($order['full_name'])) ?></p>
            <ul class="kitchen-items">
              <?php foreach ($items_by_order[$oid] ?? [] as $line): ?>
                <li><span><?= e($line['item_name']) ?></span> <b>x<?= (int)$line['quantity'] ?></b></li>
              <?php endforeach; ?>
            </ul>
            <p class="muted small">Waiting for the student at the counter.</p>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

  <?php endif; ?>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
