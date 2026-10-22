<?php
/**
 * manager/verify.php  -  QR pickup verification screen.
 *
 * The student shows the QR picture (or reads the code aloud), the
 * staff types the code here, and the system shows the right order.
 * Marking it Collected is only possible while the order is "Ready",
 * so an order can never be collected twice.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('manager');

/** Find an order by its pickup code (case and dashes do not matter). */
function find_order_by_code(string $input): ?array
{
    $code = normalize_pickup_code($input);
    if ($code === '') {
        return null;
    }
    return db_row(
        'SELECT o.order_id, o.status, o.pickup_code, o.total_amount, o.pickup_time,
                o.payment_method, o.collected_at, o.created_at, o.notes,
                u.full_name, p.payment_status,
                (SELECT SUM(quantity) FROM order_items WHERE order_id = o.order_id) AS items_total
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         LEFT JOIN payments p ON p.order_id = o.order_id
         WHERE o.pickup_code = ?',
        [$code]
    );
}

$code_input = '';
$found      = null;
$message    = null;

/* ------------------------------------------------------------------
 * Find an order / collect an order
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $code_input = post_str('code', 30);
    $action     = post_str('action', 20);
    $found      = find_order_by_code($code_input);

    if (!$found) {
        $message = ['type' => 'error', 'text' => 'No order matches this code. Check the characters and try again.'];
    } elseif ($action === 'collect') {
        // The workflow rules inside change_order_status() only allow
        // Ready -> Collected, so a second attempt is refused here.
        $result = change_order_status(
            (int)$found['order_id'],
            'Collected',
            (int)$me['user_id'],
            'manager',
            'QR pickup code verified at the counter'
        );
        $message = ['type' => $result['ok'] ? 'success' : 'error', 'text' => $result['message']];
        $found   = find_order_by_code($code_input); // refresh the status shown
    }
}

/* The dashboard links here with ?code=... so the order opens ready. */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code']) && $found === null) {
    $code_input = (string)$_GET['code'];
    $found      = find_order_by_code($code_input);
}

$recent = db_all(
    'SELECT o.order_id, o.pickup_code, o.collected_at, o.total_amount, u.full_name
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     WHERE o.status = "Collected"
     ORDER BY o.collected_at DESC
     LIMIT 8'
);

$page_title = 'Verify pickup';
require __DIR__ . '/../includes/header.php';
?>

<h1>Verify pickup</h1>
<p class="muted">Ask the student for the QR code, then type the code printed under it.</p>

<div class="card narrow">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="find">

    <label for="code">Pickup code</label>
    <input type="text" id="code" name="code" maxlength="30" style="text-transform:uppercase"
           value="<?= e($code_input) ?>" placeholder="e.g. 9F3A-71C4-B0D8-2E55"
           autocomplete="off" required>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Find order</button>
    </div>
  </form>
</div>

<?php if ($message !== null): ?>
  <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['text']) ?></div>
<?php endif; ?>

<?php if ($found !== null): ?>
  <div class="card" style="border-left:5px solid var(--orange)">
    <h2>Order #<?= (int)$found['order_id'] ?> <?= status_badge($found['status']) ?></h2>
    <p>
      <strong><?= e($found['full_name']) ?></strong> &middot;
      <?= (int)$found['items_total'] ?> item(s) &middot;
      <?= e(money($found['total_amount'])) ?><br>
      <span class="muted small">
        Pickup code <?= e($found['pickup_code']) ?> &middot;
        requested for <?= e(format_time($found['pickup_time'])) ?><br>
        Payment:
        <?= e(payment_method_label($found['payment_method'])) ?>
        <?php if ($found['payment_status'] === 'paid'): ?>
          &mdash; <span class="badge badge-paid">Paid</span>
        <?php elseif ($found['payment_status'] === 'due_at_pickup'): ?>
          &mdash; <span class="badge badge-pending">Collect the cash</span>
        <?php elseif ($found['payment_status'] === 'failed'): ?>
          &mdash; <span class="badge badge-failed">Failed</span>
        <?php else: ?>
          &mdash; <span class="badge badge-pending">Not paid</span>
        <?php endif; ?>
      </span>
    </p>

    <?php if ($found['status'] === 'Ready'): ?>
      <div class="form-actions">
        <form method="post" data-submit-once>
          <?= csrf_field() ?>
          <input type="hidden" name="code" value="<?= e($found['pickup_code']) ?>">
          <input type="hidden" name="action" value="collect">
          <button class="btn btn-success" type="submit">Hand over food &mdash; mark as Collected</button>
        </form>
      </div>

    <?php elseif ($found['status'] === 'Collected'): ?>
      <div class="alert alert-warning">
        This order was already collected on <?= e(format_datetime($found['collected_at'])) ?>.
        It cannot be collected a second time.
      </div>

    <?php else: ?>
      <div class="alert alert-info">
        This order is still &ldquo;<?= e($found['status']) ?>&rdquo;, so it cannot be handed over yet.
        <?= e(order_status_hint($found['status'])) ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<h2 class="section-title">Recently collected</h2>
<?php if (!$recent): ?>
  <?php empty_state('No pickups yet', 'Collected orders will be listed here.'); ?>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Order</th><th>Student</th><th>Total</th><th>Pickup code</th><th>Collected at</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td><a href="<?= e(url('manager/order_details.php?id=' . (int)$row['order_id'])) ?>">#<?= (int)$row['order_id'] ?></a></td>
              <td><?= e(short_name($row['full_name'])) ?></td>
              <td><?= e(money($row['total_amount'])) ?></td>
              <td><code><?= e($row['pickup_code']) ?></code></td>
              <td><?= e(format_datetime($row['collected_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
