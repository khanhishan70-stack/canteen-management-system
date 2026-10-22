<?php
/**
 * student/menu.php  -  Browse the menu by category and fill the cart.
 * Guests may look at the menu; only logged in students can order.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = current_user();

/* ------------------------------------------------------------------
 * Adding to the cart (form posted from one of the item cards)
 * NO LOGIN IS NEEDED: a plain visitor can fill a whole cart. The
 * account is only created later, at checkout, to own the order.
 * ---------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($me !== null && $me['role'] !== 'student') {
        flash_set('info', 'Please use the Student System to order food.');
        redirect(url('student/menu.php'));
    }
    verify_csrf();

    $item_id = post_int('item_id', 0);
    $qty     = post_int('qty', 1);

    if ($item_id <= 0 || $qty === null || $qty < 1 || $qty > 50) {
        flash_set('error', 'That does not look right. Please try again.');
        redirect(url('student/menu.php'));
    }

    // The item must exist AND be available right now.
    $item = db_row(
        'SELECT item_id, name FROM menu_items WHERE item_id = ? AND is_available = 1',
        [$item_id]
    );

    if (!$item) {
        flash_set('error', 'Sorry, that item is not available any more.');
        redirect(url('student/menu.php'));
    }

    $cart         = clean_cart();
    $cart[$item_id] = min(50, ($cart[$item_id] ?? 0) + $qty);
    cart_save($cart);

    flash_set('success', $qty . ' x ' . $item['name'] . ' added to your cart.');
    redirect(url('student/menu.php'));
}

/* ------------------------------------------------------------------
 * Read the menu for the page
 * ------------------------------------------------------------------ */
$categories = db_all('SELECT category_id, name FROM categories WHERE is_active = 1 ORDER BY name');
$active_cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

if ($active_cat > 0) {
    $items = db_all(
        'SELECT item_id, name, description, price, category_id
         FROM menu_items WHERE is_available = 1 AND category_id = ? ORDER BY name',
        [$active_cat]
    );
} else {
    $items = db_all(
        'SELECT item_id, name, description, price, category_id
         FROM menu_items WHERE is_available = 1 ORDER BY category_id, name'
    );
}

/** A friendly picture for each category (no image files needed). */
function category_emoji(string $name): string
{
    $map = [
        'Breakfast' => '&#127859;',
        'Rice & Meals' => '&#127834;',
        'Snacks' => '&#127853;',
        'Burgers & Sandwiches' => '&#127828;',
        'Drinks' => '&#129380;',
        'Desserts' => '&#127846;',
    ];
    return $map[$name] ?? '&#127860;';
}

$page_title = 'Menu';
require __DIR__ . '/../includes/header.php';
?>

<h1>Today's menu</h1>
<p class="muted">Pick what you like, choose a pickup time, and your food will be ready when you arrive.
  <strong>No login needed</strong> &mdash; your Rs. 500 demo wallet is ready from the start.</p>

<div class="cat-tabs">
  <a href="<?= e(url('student/menu.php')) ?>" class="<?= $active_cat === 0 ? 'active' : '' ?>">All</a>
  <?php foreach ($categories as $category): ?>
    <a href="<?= e(url('student/menu.php?cat=' . (int)$category['category_id'])) ?>"
       class="<?= $active_cat === (int)$category['category_id'] ? 'active' : '' ?>">
      <?= e($category['name']) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$items): ?>
  <?php empty_state('No food to show here yet', 'Please check another category, or come back a little later.'); ?>

<?php else: ?>
  <div class="menu-grid">
    <?php foreach ($items as $item): ?>
      <?php
        // Look up the category name for the emoji of this item.
        $emoji = '&#127860;';
        foreach ($categories as $category) {
            if ((int)$category['category_id'] === (int)$item['category_id']) {
                $emoji = category_emoji($category['name']);
                break;
            }
        }
      ?>
      <div class="card item-card">
        <div class="item-emoji"><?= $emoji ?></div>
        <h3 class="item-name"><?= e($item['name']) ?></h3>
        <p class="item-desc"><?= e($item['description']) ?></p>
        <div class="item-foot">
          <span class="price"><?= e(money($item['price'])) ?></span>

          <form method="post" class="qty-box" data-submit-once>
            <?= csrf_field() ?>
            <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
            <input type="number" name="qty" value="1" min="1" max="50" aria-label="Quantity">
            <button class="btn btn-small btn-primary" type="submit">Add</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (cart_count() > 0): ?>
  <div class="card" style="margin-top:20px">
    <p style="margin:0">
      Your cart has <strong><?= (int)cart_count() ?></strong> item(s).
      <a class="btn btn-small btn-success" href="<?= e(url('student/cart.php')) ?>">Go to cart</a>
    </p>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
