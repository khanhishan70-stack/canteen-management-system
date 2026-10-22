<?php
/**
 * student/checkout.php  -  Choose a pickup time, choose how to pay,
 * and place the order.
 *
 * The total is calculated again on the SERVER from the menu prices.
 * Nothing about money is taken from the browser.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

/*
 * NO LOGIN IS NEEDED TO ORDER FOOD.
 * A plain visitor who reaches this page gets a demo account created
 * for them on the spot (Rs. 500 welcome money) and the browser is
 * signed in silently - no form, no password, nothing to type.
 * The account only exists so the order has an owner and so the
 * student can follow it afterwards.
 */
$me = ensure_guest_account();

if (($me['role'] ?? '') !== 'student') {
    require_role('student');   // friendly "this page is not for you" page
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = post_str('action', 20);

    /* --- Demo wallet: add more fake money (presentation helper) --- */
    if ($action === 'topup') {
        db_run('UPDATE users SET balance = balance + 500 WHERE user_id = ?', [(int)$me['user_id']]);
        flash_set('success', 'Rs. 500.00 demo money added to your wallet. Total is now '
                  . money_format_balance(wallet_balance((int)$me['user_id'])) . '.');
        redirect(url('student/checkout.php'));
    }

    $pickup_time     = post_str('pickup_time', 5);
    $payment_method  = post_str('payment_method', 10);
    $notes           = post_str('notes', 255);

    /* ---------------------------------------------------------------
     * A QR scan pays out of the demo wallet, so the balance is checked
     * on the SERVER before the order even exists. The browser cannot
     * skip this by editing the form.
     * ------------------------------------------------------------- */
    if ($payment_method === 'online') {
        $cart_total = 0.0;
        foreach (clean_cart() as $item_id => $qty) {
            $price = db_value('SELECT price FROM menu_items WHERE item_id = ? AND is_available = 1', [$item_id]);
            if ($price === null) {
                flash_set('error', 'Some items in your cart are no longer available. Please check the menu again.');
                redirect(url('student/checkout.php'));
            }
            $cart_total += (float)$price * (int)$qty;
        }
        $cart_total = round($cart_total, 2);
        $balance    = demo_balance();

        if ($cart_total > 0 && $balance < $cart_total) {
            flash_set('error', 'Your wallet has ' . money_format_balance($balance)
                      . ' but this order needs ' . money_format_balance($cart_total)
                      . '. Add demo money below, or choose Cash on Delivery (COD).');
            redirect(url('student/checkout.php'));
        }
    }

    $result = create_order_from_cart((int)$me['user_id'], clean_cart(), $pickup_time, $payment_method);

    if (!$result['ok']) {
        flash_set('error', $result['message']);
        redirect(url('student/checkout.php'));
    }

    // Save the note (optional) - it is not part of the order creation.
    if ($notes !== '') {
        db_run('UPDATE orders SET notes = ? WHERE order_id = ? AND user_id = ?',
               [$notes, $result['order_id'], $me['user_id']]);
    }

    // The cart is finished, empty it.
    cart_save([]);

    $order_id = $result['order_id'];

    if ($payment_method === 'online') {
        // The QR payment screen - the student scans it with a phone.
        flash_set('info', 'Order #' . $order_id . ' created. Scan the QR code with your phone to pay from your wallet (demo).');
        redirect(url('student/payment.php?order=' . $order_id));
    }

    flash_set('success', 'Order #' . $order_id . ' placed (COD). The staff has been notified - pay cash when you collect it.');
    redirect(url('student/order_details.php?id=' . $order_id));
}

/* ------------------------------------------------------------------
 * Show the checkout page
 * ------------------------------------------------------------------ */
$cart  = clean_cart();
$total = 0.0;
$rows  = [];

if ($cart) {
    $ids   = array_keys($cart);
    $in    = implode(',', array_fill(0, count($ids), '?'));
    $items = db_all(
        "SELECT item_id, name, price, is_available FROM menu_items WHERE item_id IN ($in) AND is_available = 1",
        $ids
    );
    foreach ($items as $item) {
        $qty  = $cart[(int)$item['item_id']];
        $line = round((float)$item['price'] * $qty, 2);
        $total += $line;
        $rows[] = ['name' => $item['name'], 'qty' => $qty, 'line' => $line];
    }
}
$total = round($total, 2);
$slots = pickup_slots();

$balance    = demo_balance();
$qr_enabled = ($total <= $balance);

$page_title = 'Checkout';
require __DIR__ . '/../includes/header.php';
?>

<h1>Checkout</h1>

<div class="wallet-panel">
  <div>
    <div class="wallet-label">&#128176; Your demo wallet</div>
    <div class="wallet-amount <?= $qr_enabled ? '' : 'wallet-low' ?>">
      <?= e(money_format_balance($balance)) ?>
    </div>
    <div class="wallet-label">Free demo money, given when the account was created.</div>
  </div>
  <form method="post" class="inline-form" data-submit-once>
    <?= csrf_field() ?>
    <button class="btn btn-small btn-muted" type="submit" name="action" value="topup">
      + Add Rs. 500 demo money
    </button>
  </form>
</div>

<?php if (!$rows): ?>
  <?php empty_state('Nothing to check out yet', 'Add some food to your cart first.'); ?>
  <p class="center"><a class="btn btn-primary" href="<?= e(url('student/menu.php')) ?>">Open the menu</a></p>

<?php else: ?>

  <div class="card">
    <h2>Your order</h2>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Item</th><th>Qty</th><th>Line total</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e($row['name']) ?></td>
              <td><?= (int)$row['qty'] ?></td>
              <td><?= e(money($row['line'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="total-row">
      <span>Total to pay</span>
      <span><?= e(money($total)) ?></span>
    </div>
  </div>

  <form method="post" data-submit-once>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="place_order">

    <div class="card">
      <h2>When will you collect it?</h2>
      <label for="pickup_time">Pickup time (today)</label>
      <select id="pickup_time" name="pickup_time" required>
        <?php foreach ($slots as $slot): ?>
          <option value="<?= e($slot) ?>"><?= e(format_time($slot)) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="field-hint">The times offered start about 10 minutes from now.</p>
    </div>

    <div class="card">
      <h2>How would you like to pay?</h2>
      <div class="choice-list">
        <label class="choice">
          <input type="radio" name="payment_method" value="online"
                 <?= $qr_enabled ? 'checked' : 'disabled' ?>>
          <span>
            <strong>&#128247; QR code scan</strong>
            <span>A QR code appears on this laptop. Scan it with your
              <strong>phone camera</strong>, confirm on the phone, and
              <?= e(money($total)) ?> comes out of your
              <?= e(money_format_balance($balance)) ?> demo wallet.
              <?php if (!$qr_enabled): ?>
                <b class="wallet-low">Not enough balance for this order - add demo money above.</b>
              <?php endif; ?>
            </span>
          </span>
        </label>

        <label class="choice">
          <input type="radio" name="payment_method" value="cash"
                 <?= $qr_enabled ? '' : 'checked' ?>>
          <span>
            <strong>&#128176; Cash on Delivery (COD)</strong>
            <span>Pay cash at the counter when you collect your food.
              Nothing is taken from your wallet.</span>
          </span>
        </label>
      </div>

      <label for="notes">Note for the kitchen (optional)</label>
      <input type="text" id="notes" name="notes" maxlength="255" placeholder="e.g. less chilli please">
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Place order</button>
      <a class="btn btn-muted" href="<?= e(url('student/cart.php')) ?>">Back to cart</a>
    </div>
  </form>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
