<?php
/**
 * manager/menu.php  -  Add, edit and disable menu items.
 * A disabled item stays in the database (so old orders keep their
 * link) but students can no longer order it.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = require_role('manager');

$errors = [];

/* ------------------------------------------------------------------
 * Save changes (adding categories, adding items, editing, switching)
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_str('action', 20);

    if ($action === 'add_category') {
        $name = post_str('name', 60);
        if (mb_strlen($name) < 2) {
            $errors[] = 'A category name needs at least 2 letters.';
        } elseif (db_row('SELECT category_id FROM categories WHERE name = ?', [$name])) {
            $errors[] = 'A category with this name already exists.';
        } else {
            db_run('INSERT INTO categories (name, description) VALUES (?, ?)',
                   [$name, post_str('description', 255) ?: null]);
            flash_set('success', 'Category "' . $name . '" added.');
            redirect(url('manager/menu.php'));
        }
    }

    if ($action === 'add_item' || $action === 'edit_item') {
        $name        = post_str('name', 100);
        $description = post_str('description', 255);
        $category_id = post_int('category_id', 0);
        $price_raw   = post_str('price', 12);
        $price       = filter_var($price_raw, FILTER_VALIDATE_FLOAT);
        $item_id     = post_int('item_id', 0);

        if (mb_strlen($name) < 2) {
            $errors[] = 'The item name needs at least 2 letters.';
        }
        if ($price === false || $price <= 0 || $price > 10000) {
            $errors[] = 'The price must be a number greater than 0 (and below 10000).';
        }
        if (!db_row('SELECT category_id FROM categories WHERE category_id = ?', [$category_id])) {
            $errors[] = 'Please choose a valid category.';
        }

        if (!$errors) {
            if ($action === 'add_item') {
                db_run(
                    'INSERT INTO menu_items (category_id, name, description, price) VALUES (?, ?, ?, ?)',
                    [$category_id, $name, $description ?: null, $price]
                );
                flash_set('success', 'Item "' . $name . '" added to the menu.');
            } else {
                db_run(
                    'UPDATE menu_items SET category_id = ?, name = ?, description = ?, price = ?
                     WHERE item_id = ?',
                    [$category_id, $name, $description ?: null, $price, $item_id]
                );
                flash_set('success', 'Item "' . $name . '" updated.');
            }
            redirect(url('manager/menu.php'));
        }

        // Something was wrong: keep the values the manager typed.
        $old_item = [
            'item_id'     => $item_id,
            'name'        => $name,
            'description' => $description,
            'price'       => $price_raw,
            'category_id' => $category_id,
        ];
    }

    if ($action === 'toggle_item') {
        $item_id = post_int('item_id', 0);
        $item    = db_row('SELECT item_id, name, is_available FROM menu_items WHERE item_id = ?', [$item_id]);

        if ($item) {
            $new = ((int)$item['is_available'] === 1) ? 0 : 1;
            db_run('UPDATE menu_items SET is_available = ? WHERE item_id = ?', [$new, $item_id]);
            flash_set('success', $item['name'] . ($new ? ' is available again.' : ' is now disabled.'));
        }
        redirect(url('manager/menu.php'));
    }
}

/* ------------------------------------------------------------------
 * Show the page
 * ------------------------------------------------------------------ */
$categories = db_all('SELECT category_id, name FROM categories ORDER BY name');
$items      = db_all(
    'SELECT i.item_id, i.name, i.description, i.price, i.is_available, c.name AS category_name
     FROM menu_items i
     JOIN categories c ON c.category_id = i.category_id
     ORDER BY c.name, i.name'
);

// Editing an item? (?edit=12 shows the form filled in at the top)
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($items as $item) {
        if ((int)$item['item_id'] === $edit_id) {
            $edit_item = $item;
            break;
        }
    }
}
if (isset($old_item) && !$edit_item) {
    $edit_item = $old_item + ['is_available' => 1, 'category_name' => ''];
}

$page_title = 'Menu management';
require __DIR__ . '/../includes/header.php';
?>

<h1>Menu management</h1>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="form-row two">

  <!-- ---------------- Add or edit an item ---------------- -->
  <div class="card">
    <h2><?= $edit_item && !empty($edit_item['item_id']) ? 'Edit item' : 'Add a menu item' ?></h2>

    <form method="post" data-submit-once>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $edit_item && !empty($edit_item['item_id']) ? 'edit_item' : 'add_item' ?>">
      <?php if ($edit_item && !empty($edit_item['item_id'])): ?>
        <input type="hidden" name="item_id" value="<?= (int)$edit_item['item_id'] ?>">
      <?php endif; ?>

      <label for="item_name">Item name</label>
      <input type="text" id="item_name" name="name" maxlength="100"
             value="<?= e($edit_item['name'] ?? '') ?>" placeholder="e.g. Chicken Biryani" required>

      <label for="item_desc">Short description</label>
      <input type="text" id="item_desc" name="description" maxlength="255"
             value="<?= e($edit_item['description'] ?? '') ?>" placeholder="e.g. served with raita">

      <label for="item_price">Price (<?= e(trim(CURRENCY)) ?>)</label>
      <input type="text" id="item_price" name="price" inputmode="decimal"
             value="<?= e($edit_item['price'] ?? '') ?>" placeholder="e.g. 250" required>

      <label for="item_category">Category</label>
      <select id="item_category" name="category_id" required>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int)$category['category_id'] ?>"
            <?= isset($edit_item['category_id']) && (int)$edit_item['category_id'] === (int)$category['category_id'] ? 'selected' : '' ?>>
            <?= e($category['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $edit_item && !empty($edit_item['item_id']) ? 'Save changes' : 'Add item' ?></button>
        <?php if ($edit_item && !empty($edit_item['item_id'])): ?>
          <a class="btn btn-muted" href="<?= e(url('manager/menu.php')) ?>">Cancel edit</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ---------------- Add a category ---------------- -->
  <div class="card">
    <h2>Add a category</h2>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_category">

      <label for="cat_name">Category name</label>
      <input type="text" id="cat_name" name="name" maxlength="60" placeholder="e.g. Salads" required>

      <label for="cat_desc">Description (optional)</label>
      <input type="text" id="cat_desc" name="description" maxlength="255" placeholder="e.g. fresh and healthy">

      <div class="form-actions">
        <button class="btn btn-muted" type="submit">Add category</button>
      </div>
    </form>

    <p class="muted small" style="margin-top:14px">
      Current categories:
      <?php foreach ($categories as $index => $category): ?>
        <?= e($category['name']) ?><?= $index < count($categories) - 1 ? ', ' : '' ?>
      <?php endforeach; ?>
    </p>
  </div>
</div>

<h2 class="section-title">Items on the menu (<?= count($items) ?>)</h2>

<?php if (!$items): ?>
  <?php empty_state('No menu items yet', 'Use the form above to add the first item.'); ?>

<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Item</th><th>Category</th><th>Price</th><th>Available</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td class="wrap">
                <strong><?= e($item['name']) ?></strong>
                <?php if ($item['description']): ?>
                  <div class="small muted"><?= e($item['description']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($item['category_name']) ?></td>
              <td><?= e(money($item['price'])) ?></td>
              <td>
                <?php if ((int)$item['is_available'] === 1): ?>
                  <span class="badge badge-paid">Available</span>
                <?php else: ?>
                  <span class="badge badge-cancelled">Disabled</span>
                <?php endif; ?>
              </td>
              <td class="nowrap">
                <a class="btn btn-small btn-muted" href="<?= e(url('manager/menu.php?edit=' . (int)$item['item_id'])) ?>">Edit</a>
                <form method="post" class="inline-form"
                      data-confirm="<?= (int)$item['is_available'] === 1 ? 'Disable this item? Students will not be able to order it.' : 'Make this item available again?' ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                  <button class="btn btn-small <?= (int)$item['is_available'] === 1 ? 'btn-danger' : 'btn-success' ?>"
                          type="submit" name="action" value="toggle_item">
                    <?= (int)$item['is_available'] === 1 ? 'Disable' : 'Enable' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
