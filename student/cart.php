<?php
/**
 * student/cart.php  -  Check the cart and change the quantities.
 * The cart lives in the PHP session, so it is saved per browser.
 * NO LOGIN NEEDED - a guest can fill and change the cart freely.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = current_user();   // may be null - guests are welcome here

/* ------------------------------------------------------------------
 * Update one quantity, remove one item, or empty the whole cart
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action  = post_str('action', 20);
    $item_id = post_int('item_id', 0);
    $cart    = clean_cart();

    if ($action === 'update' && isset($cart[$item_id])) {
        $qty = post_int('qty', 0);
        if ($qty === null || $qty < 1 || $qty > 50) {
            flash_set('error', 'Quantity must be between 1 and 50.');
        } else {
            $cart[$item_id] = $qty;
            flash_set('success', 'Cart updated.');
        }
    } elseif ($action === 'remove' && isset($cart[$item_id])) {
        unset($cart[$item_id]);
        flash_set('success', 'Item removed from your cart.');
    } elseif ($action === 'clear') {
        $cart = [];
        flash_set('info', 'Your cart is empty now.');
    }

    cart_save($cart);
    redirect(url('student/cart.php'));
}

/* ------------------------------------------------------------------
 * Show the cart with live prices from the database
 * ------------------------------------------------------------------ */
$cart = clean_cart();
$rows = [];
$total = 0.0;

if ($cart) {
    $ids   = array_keys($cart);
    $in    = implode(',', array_fill(0, count($ids), '?'));
    $items = db_all(
        "SELECT item_id, name, price, is_available FROM menu_items WHERE item_id IN ($in)",
        $ids
    );

    foreach ($items as $item) {
        $qty  = $cart[(int)$item['item_id']];
        $line = round((float)$item['price'] * $qty, 2);
        $total += $line;
        $rows[] = [
            'item_id'   => (int)$item['item_id'],
            'name'      => $item['name'],
            'price'     => (float)$item['price'],
            'qty'       => $qty,
            'line'      => $line,
            'available' => (int)$item['is_available'] === 1,
        ];
    }
}
$total = round($total, 2);

$page_title = 'My cart';
require __DIR__ . '/../includes/header.php';
?>

<h1>Your cart</h1>

<?php if (!$rows): ?>
  <?php empty_state('Your cart is empty', 'Browse the menu and add something tasty!'); ?>
  <p class="center"><a class="btn btn-primary" href="<?= e(url('student/menu.php')) ?>">Open the menu</a></p>

<?php else: ?>

  <?php foreach ($rows as $row): ?>
    <div class="card">
      <?php if (!$row['available']): ?>
        <div class="alert alert-warning">This item is no longer available. Please remove it.</div>
      <?php endif; ?>

      <div class="cart-line">
        <div>
          <div class="cart-name"><?= e($row['name']) ?></div>
          <div class="muted small"><?= e(money($row['price'])) ?> each</div>
        </div>
        <div class="nowrap"><strong><?= e(money($row['line'])) ?></strong></div>
      </div>

      <form method="post" class="qty-box" style="margin-top:10px">
        <?= csrf_field() ?>
        <input type="hidden" name="item_id" value="<?= (int)$row['item_id'] ?>">

        <label class="small" style="margin:0" for="qty<?= (int)$row['item_id'] ?>">Quantity</label>
        <input type="number" id="qty<?= (int)$row['item_id'] ?>" name="qty"
               value="<?= (int)$row['qty'] ?>" min="1" max="50">

        <button class="btn btn-small btn-muted" type="submit" name="action" value="update">Update</button>
        <button class="btn btn-small btn-danger" type="submit" name="action" value="remove"
                formnovalidate>Remove</button>
      </form>
    </div>
  <?php endforeach; ?>

  <div class="card">
    <div class="total-row">
      <span>Total</span>
      <span><?= e(money($total)) ?></span>
    </div>

    <div class="form-actions">
      <a class="btn btn-primary" href="<?= e(url('student/checkout.php')) ?>">Choose pickup time &amp; pay</a>
      <a class="btn btn-muted" href="<?= e(url('student/menu.php')) ?>">Add more food</a>
    </div>
  </div>

  <form method="post" data-confirm="Empty your whole cart?">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="clear">
    <button class="btn btn-small btn-ghost" type="submit"
            style="background:#f1f3f5;color:#868e96">Clear cart</button>
  </form>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
