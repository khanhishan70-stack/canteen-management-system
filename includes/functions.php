<?php
/**
 * functions.php  -  Small helper functions used by every page.
 * Read this file first: it explains most of the logic of the project.
 */

/* ==================================================================
 * 1. DATABASE HELPERS  (every query in the site goes through these,
 *    so every query is a prepared statement)
 * ================================================================== */

/** Run a prepared statement and return the statement object. */
function db_run(string $sql, array $params = []): PDOStatement
{
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Run a SELECT and return ALL rows as an associative array. */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/** Run a SELECT and return ONE row (or null when there is no row). */
function db_row(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Run a SELECT and return only the first column of the first row. */
function db_value(string $sql, array $params = [])
{
    $value = db_run($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

/** Run an INSERT / UPDATE / DELETE and return how many rows changed. */
function db_exec(string $sql, array $params = []): int
{
    return db_run($sql, $params)->rowCount();
}

/** Auto-increment id of the last INSERT on this connection. */
function db_last_id(): int
{
    global $conn;
    return (int)$conn->lastInsertId();
}

/* ==================================================================
 * 2. OUTPUT AND REQUEST HELPERS
 * ================================================================== */

/**
 * Escape text before printing it into HTML.
 * This is the main protection against cross-site scripting (XSS).
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Build a link inside the project, e.g. url('student/menu.php'). */
function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/** Send the browser to another page and stop the script. */
function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

/** Print a price, e.g. "Rs. 250.00". */
function money($amount): string
{
    return CURRENCY . number_format((float)$amount, 2);
}

/** Format a date + time for the screen, e.g. "22 Oct 2026, 01:15 PM". */
function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    return date('d M Y, h:i A', strtotime($value));
}

/** Format a time value, e.g. "12:30 PM". */
function format_time(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    // TIME columns look like "12:30:00"
    return date('h:i A', strtotime($value));
}

/** Read an integer from the form. Returns $default when it is not a number. */
function post_int(string $key, ?int $default = null): ?int
{
    if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
        return $default;
    }
    $value = filter_var(trim((string)$_POST[$key]), FILTER_VALIDATE_INT);
    return $value === false ? $default : (int)$value;
}

/** Read trimmed text from the form and cut it to a maximum length. */
function post_str(string $key, int $max = 255): string
{
    if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
        return '';
    }
    return mb_substr(trim((string)$_POST[$key]), 0, $max);
}

/* ==================================================================
 * 3. FLASH MESSAGES  (one message shown after a redirect)
 * ================================================================== */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Return the waiting message once, then delete it. */
function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $message;
}

/* ==================================================================
 * 3B. DEMO WALLET  (every student starts with Rs. 500 demo money)
 * ================================================================== */

/** Money a person has in the demo wallet right now. */
function wallet_balance(int $user_id): float
{
    return (float)(db_value('SELECT balance FROM users WHERE user_id = ?', [$user_id]) ?? 0);
}

/** Show a price as the wallet sees it, e.g. "Rs. 500.00". */
function money_format_balance(float $balance): string
{
    return CURRENCY . number_format($balance, 2);
}

/* ==================================================================
 * 3C. CROSS-DEVICE NOTIFICATIONS
 *     A notification is addressed to a ROLE (manager or chef), never
 *     to one person. Every phone / laptop logged in with that role
 *     polls api/notifications.php and shows the unread rows as a
 *     pop-up. This is how the staff phone hears about a new order and
 *     how the chef phone hears about an order sent by the staff.
 * ================================================================== */

/** Queue one pop-up message for every device in a role. */
function notify_role(string $role, string $title, string $message, ?int $order_id = null): void
{
    if (!in_array($role, ['manager', 'chef'], true)) {
        return;
    }
    db_run(
        'INSERT INTO notifications (role, order_id, title, message) VALUES (?, ?, ?, ?)',
        [$role, $order_id, mb_substr($title, 0, 100), mb_substr($message, 0, 255)]
    );
}

/** Unread pop-ups waiting for one role (used by api/notifications.php). */
function notifications_take(string $role, int $limit = 10): array
{
    $rows = db_all(
        'SELECT notification_id, order_id, title, message, created_at
         FROM notifications
         WHERE role = ? AND is_read = 0
         ORDER BY created_at DESC, notification_id DESC
         LIMIT ' . $limit,
        [$role]
    );

    if ($rows) {
        $ids = array_map(function ($row) { return (int)$row['notification_id']; }, $rows);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        db_run("UPDATE notifications SET is_read = 1 WHERE notification_id IN ($in)", $ids);
    }
    return $rows;
}

/**
 * How the payment method is written on the screens.
 * The database keeps the original words "online" and "cash":
 *   online -> the student scanned the QR code with a phone
 *   cash   -> Cash On Delivery, paid at the counter
 */
function payment_method_label(?string $method): string
{
    $map = [
        'online' => 'QR Scan',
        'cash'   => 'COD',
    ];
    return $map[$method ?? ''] ?? '-';
}

function payment_method_badge(?string $method): string
{
    $class = $method === 'online' ? 'badge-accepted' : 'badge-paid';
    return '<span class="badge ' . $class . '">' . e(payment_method_label($method)) . '</span>';
}

/* ==================================================================
 * 3D. GUEST ORDERING  (NO login is needed to buy food)
 *
 * A visitor opens the menu, fills the cart and checks out without ever
 * seeing a login form. At checkout, ensure_guest_account() quietly
 * creates a demo account for them (the orders table needs an owner)
 * and signs the browser in, so the payment page, the order tracking
 * and the pickup QR all work straight away.
 * ================================================================== */

/**
 * The demo wallet of whoever is looking at the screen.
 * Works for a signed-in student AND for a plain browsing guest.
 */
function demo_balance(): float
{
    $me = current_user();
    if ($me !== null) {
        return (float)($me['balance'] ?? 0);
    }

    $guest_id = (int)($_SESSION['guest_user_id'] ?? 0);
    if ($guest_id > 0) {
        return wallet_balance($guest_id);
    }
    return (float)DEMO_WELCOME_BALANCE;
}

/**
 * Return the account that will own the order.
 *
 *  - already signed in  -> that account
 *  - plain guest        -> a brand new demo account, made right now,
 *                          with the welcome money, and the browser is
 *                          signed in silently (no form, no password).
 *
 * @return array the user row (user_id, full_name, email, role, balance)
 */
function ensure_guest_account(): array
{
    $me = current_user();
    if ($me !== null) {
        return $me;
    }

    /* This browser ordered before and still remembers its account. */
    $guest_id = (int)($_SESSION['guest_user_id'] ?? 0);
    if ($guest_id > 0) {
        if (db_value('SELECT user_id FROM users WHERE user_id = ? AND is_active = 1', [$guest_id])) {
            $_SESSION['user_id'] = $guest_id;   // silent sign-in
            $resumed = current_user();
            if ($resumed !== null) {
                return $resumed;
            }
        }
        unset($_SESSION['guest_user_id']);
    }

    /* First order from this browser: make the demo account. */
    db_run(
        'INSERT INTO users (full_name, email, password_hash, role, balance)
         VALUES (?, ?, ?, "student", ?)',
        [
            'Guest Student',
            'guest_' . bin2hex(random_bytes(8)) . '@guest.local',
            password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), // never used to log in
            DEMO_WELCOME_BALANCE,
        ]
    );

    $guest_id = db_last_id();
    $_SESSION['guest_user_id'] = $guest_id;
    $_SESSION['user_id']       = $guest_id;    // silent sign-in

    $me = current_user();
    return $me !== null ? $me : ['user_id' => $guest_id, 'role' => 'student'];
}

/* ==================================================================
 * 4. ORDER STATUS WORKFLOW  (the heart of the project)
 * ================================================================== */

/** The complete list of statuses used by the system. */
function order_statuses(): array
{
    return [
        'Pending Payment',
        'Paid',
        'Sent to Chef',
        'Accepted',
        'Preparing',
        'Ready',
        'Collected',
        'Cancelled',
        'Payment Failed',
    ];
}

/** CSS colour class for a status badge. */
function status_class(string $status): string
{
    $map = [
        'Pending Payment' => 'badge-pending',
        'Paid'            => 'badge-paid',
        'Sent to Chef'    => 'badge-sent',
        'Accepted'        => 'badge-accepted',
        'Preparing'       => 'badge-preparing',
        'Ready'           => 'badge-ready',
        'Collected'       => 'badge-collected',
        'Cancelled'       => 'badge-cancelled',
        'Payment Failed'  => 'badge-failed',
    ];
    return $map[$status] ?? 'badge-pending';
}

/** A coloured status badge ready to print. */
function status_badge(string $status): string
{
    return '<span class="badge ' . status_class($status) . '">' . e($status) . '</span>';
}

/**
 * Which statuses may come AFTER $current, if the person has $role?
 *
 * This one function is what stops invalid status jumps:
 *   * a chef can never mark an order "Collected"
 *   * a manager can never mark an order "Preparing"
 *   * nothing can move backwards, and finished orders cannot change
 */
function allowed_next_statuses(string $current, string $role): array
{
    // Which statuses can follow which one.
    $next = [
        'Pending Payment' => ['Paid', 'Payment Failed', 'Sent to Chef', 'Cancelled'],
        'Payment Failed'  => ['Paid', 'Cancelled'],
        'Paid'            => ['Sent to Chef', 'Cancelled'],
        'Sent to Chef'    => ['Accepted', 'Cancelled'],
        'Accepted'        => ['Preparing', 'Cancelled'],
        'Preparing'       => ['Ready'],
        'Ready'           => ['Collected'],
        'Collected'       => [],
        'Cancelled'       => [],
    ];

    // Which role is allowed to move an order TO each status.
    $who = [
        'Paid'           => ['student'],  // only through the payment page
        'Payment Failed' => ['student'],  // only through the payment page
        'Sent to Chef'   => ['manager'],
        'Accepted'       => ['chef'],
        'Preparing'      => ['chef'],
        'Ready'          => ['chef'],
        'Collected'      => ['manager'],
        'Cancelled'      => ['student', 'manager'],
    ];

    $allowed = [];
    foreach ($next[$current] ?? [] as $status) {
        if (in_array($role, $who[$status] ?? [], true)) {
            $allowed[] = $status;
        }
    }
    return $allowed;
}

/**
 * Move an order to a new status.
 *
 * This is the ONLY place in the project that changes orders.status.
 * It re-checks the workflow rules, writes the history row and updates
 * the payment table, all inside one database transaction.
 *
 * @return array ['ok' => bool, 'message' => string]
 */
function change_order_status(
    int $order_id,
    string $new_status,
    ?int $actor_id,
    string $actor_role,
    string $note = ''
): array {
    global $conn;

    if (!in_array($new_status, order_statuses(), true)) {
        return ['ok' => false, 'message' => 'Unknown order status.'];
    }

    try {
        $conn->beginTransaction();

        // Lock the row so two people cannot change the same order twice.
        $stmt = $conn->prepare(
            'SELECT order_id, status, payment_method, user_id, total_amount
             FROM orders WHERE order_id = ? FOR UPDATE'
        );
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            $conn->rollBack();
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        $current = $order['status'];

        // Already in the status somebody is trying to set again?
        if ($current === $new_status) {
            $conn->rollBack();
            return ['ok' => false, 'message' => 'This order is already marked as ' . $new_status . '.'];
        }

        // Workflow check: is this move allowed for this role?
        if (!in_array($new_status, allowed_next_statuses($current, $actor_role), true)) {
            $conn->rollBack();
            return ['ok' => false, 'message' => 'An order cannot move from "' . $current . '" to "' . $new_status . '".'];
        }

        // ---- Update the order itself -------------------------------
        // When the order is collected we also store the collection time.
        if ($new_status === 'Collected') {
            $stmt = $conn->prepare('UPDATE orders SET status = ?, collected_at = NOW() WHERE order_id = ?');
        } else {
            $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
        }
        $stmt->execute([$new_status, $order_id]);

        // ---- Side effects on the payment record --------------------
        if ($new_status === 'Paid') {
            $amount = (float)$order['total_amount'];

            // A QR scan pays straight out of the demo wallet.
            // The student row is locked too, so two phones paying at
            // the very same moment can never spend the same rupee twice.
            if ($order['payment_method'] === 'online') {
                $stmt = $conn->prepare('SELECT balance FROM users WHERE user_id = ? FOR UPDATE');
                $stmt->execute([(int)$order['user_id']]);
                $balance = (float)($stmt->fetchColumn() ?: 0);

                if ($balance < $amount) {
                    $conn->rollBack();
                    return [
                        'ok'      => false,
                        'message' => 'Not enough demo wallet balance. Your wallet has '
                                   . money_format_balance($balance) . ' but this order needs '
                                   . money_format_balance($amount) . '. Please pay Cash on Delivery instead.',
                    ];
                }

                $stmt = $conn->prepare('UPDATE users SET balance = balance - ? WHERE user_id = ?');
                $stmt->execute([$amount, (int)$order['user_id']]);
            }

            $ref = 'DEMO-TXN-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $conn->prepare(
                "UPDATE payments SET payment_status = 'paid', paid_at = NOW(), transaction_ref = COALESCE(transaction_ref, ?)
                 WHERE order_id = ?"
            );
            $stmt->execute([$ref, $order_id]);

            // Pop-up on every staff device: this order is now paid.
            if ($order['payment_method'] === 'online') {
                notify_role(
                    'manager',
                    'Payment confirmed - order #' . $order_id,
                    'QR scan payment of ' . money_format_balance($amount) . ' received.',
                    $order_id
                );
            }
        }

        if ($new_status === 'Payment Failed') {
            $stmt = $conn->prepare("UPDATE payments SET payment_status = 'failed' WHERE order_id = ?");
            $stmt->execute([$order_id]);
        }

        // When food is collected, cash orders are finally paid.
        if ($new_status === 'Collected') {
            $stmt = $conn->prepare(
                "UPDATE payments SET payment_status = 'paid', paid_at = NOW()
                 WHERE order_id = ? AND payment_status = 'due_at_pickup'"
            );
            $stmt->execute([$order_id]);
        }

        // ---- Remember the step in the history table ----------------
        $stmt = $conn->prepare(
            'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$order_id, $current, $new_status, $actor_id, $note !== '' ? $note : null]);

        // ---- Pop-up on every chef device --------------------------
        // The staff pressed "Send to chef", so the kitchen phones ring.
        if ($new_status === 'Sent to Chef') {
            $item_count = (int)(db_value(
                'SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = ?',
                [$order_id]
            ) ?? 0);
            notify_role(
                'chef',
                'New order #' . $order_id,
                'Cook now - ' . $item_count . ' item(s), pickup '
                . format_time((string)(db_value('SELECT pickup_time FROM orders WHERE order_id = ?', [$order_id]) ?? '')) . '.',
                $order_id
            );
        }

        $conn->commit();
        return ['ok' => true, 'message' => 'Order #' . $order_id . ' is now "' . $new_status . '".'];

    } catch (Exception $ex) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Status change failed: ' . $ex->getMessage());
        return ['ok' => false, 'message' => 'The order could not be updated. Please try again.'];
    }
}

/* ==================================================================
 * 5. PICKUP CODE (this is what the QR code picture contains)
 * ================================================================== */

/**
 * Create a code that is impossible to guess, e.g. "9F3A-71C4-B0D8-2E55".
 * It is built from 16 random hexadecimal characters (64 bits).
 * Only THIS string is turned into a QR picture - never a name,
 * a phone number, a price or a password.
 */
function generate_pickup_code(): string
{
    $raw = strtoupper(bin2hex(random_bytes(8))); // 16 characters
    return substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-'
         . substr($raw, 8, 4) . '-' . substr($raw, 12, 4);
}

/** Tidy what the staff typed, so "9f3a71c4b0d82e55" also works. */
function normalize_pickup_code(string $input): string
{
    $clean = preg_replace('/[^A-Za-z0-9]/', '', $input);
    if ($clean === null || strlen($clean) !== 16) {
        return '';
    }
    return implode('-', str_split(strtoupper($clean), 4));
}

/**
 * Create the secret token that hides inside the PAYMENT QR code.
 * It is 32 random hexadecimal characters (128 bits) - impossible to
 * guess. Only this code is put in the QR picture, never a name,
 * a card number, a phone number or a password.
 */
function generate_payment_token(): string
{
    return bin2hex(random_bytes(16)); // 32 characters
}

/**
 * A full web address that a phone can open after scanning a QR code,
 * e.g. "http://192.168.1.5:8080/canteen/pay.php?token=8f3a..."
 *
 * It uses the same address the student is browsing with, so if the
 * site is opened as http://localhost/... the QR contains localhost,
 * and if it is opened with the computer's IP address (which is what
 * a phone needs on the same WiFi) the QR contains that IP.
 */
function absolute_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

/* ==================================================================
 * 6. CART  (kept in the PHP session - no database table needed)
 * ================================================================== */

function cart_get(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_save(array $cart): void
{
    $_SESSION['cart'] = $cart;
}

/** Number of items in the cart, e.g. 2 x Samosa + 1 x Tea = 3. */
function cart_count(): int
{
    $total = 0;
    foreach (cart_get() as $qty) {
        $total += (int)$qty;
    }
    return $total;
}

/**
 * Read the cart and keep only sane values:
 * item id = positive whole number, quantity = whole number between 1 and 50.
 */
function clean_cart(): array
{
    $clean = [];
    foreach (cart_get() as $item_id => $qty) {
        $item_id = (int)$item_id;
        $qty     = (int)$qty;
        if ($item_id > 0 && $qty >= 1 && $qty <= 50) {
            $clean[$item_id] = $qty;
        }
    }
    return $clean;
}

/* ==================================================================
 * 7. PICKUP TIME SLOTS
 * ================================================================== */

/**
 * Offer the next pickup times, always in the future:
 * the next 8 quarter-hours starting 10 minutes from now.
 */
function pickup_slots(int $count = 8): array
{
    $slots = [];
    $start = (int)(ceil((time() + 10 * 60) / 900) * 900); // round up to 15 min
    for ($i = 0; $i < $count; $i++) {
        $slots[] = date('H:i', $start + $i * 900);
    }
    return $slots;
}

/** A pickup time is correct when it looks like HH:MM and is not in the past. */
function valid_pickup_time(string $value): bool
{
    if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
        return false;
    }
    $timestamp = strtotime('today ' . $value);
    return $timestamp !== false && $timestamp >= time() - 600;
}

/* ==================================================================
 * 8. ORDER CREATION  (checkout)
 * ================================================================== */

/**
 * Build a new order from the shopping cart.
 *
 * IMPORTANT: the price is read from the DATABASE here.
 * The browser never sends a price, so nobody can change the total
 * by editing the form. The name and price are also copied into
 * order_items, so later menu changes never rewrite old orders.
 *
 * @return array ['ok' => bool, 'message' => string, 'order_id' => int]
 */
function create_order_from_cart(
    int $user_id,
    array $cart,
    string $pickup_time,
    string $payment_method
): array {
    global $conn;

    if ($payment_method !== 'online' && $payment_method !== 'cash') {
        return ['ok' => false, 'message' => 'Please choose a payment method.', 'order_id' => 0];
    }

    if (!valid_pickup_time($pickup_time)) {
        return ['ok' => false, 'message' => 'Please choose a pickup time from the list.', 'order_id' => 0];
    }

    if (count($cart) === 0) {
        return ['ok' => false, 'message' => 'Your cart is empty.', 'order_id' => 0];
    }

    // Ask MySQL which of the wanted items still exist and are available.
    $ids      = array_keys($cart);
    $in       = implode(',', array_fill(0, count($ids), '?'));
    $items    = db_all(
        "SELECT item_id, name, price FROM menu_items WHERE item_id IN ($in) AND is_available = 1",
        $ids
    );

    if (count($items) !== count($cart)) {
        return ['ok' => false, 'message' => 'Some items in your cart are no longer available. Please check the menu again.', 'order_id' => 0];
    }

    // ---- Calculate the total on the SERVER ------------------------
    $total   = 0.0;
    foreach ($items as $item) {
        $total += (float)$item['price'] * $cart[(int)$item['item_id']];
    }
    $total = round($total, 2);

    try {
        $conn->beginTransaction();

        // ---- The order -------------------------------------------
        $pickup_code = generate_pickup_code();
        $stmt = $conn->prepare(
            'INSERT INTO orders (user_id, pickup_code, pickup_time, payment_method, status, total_amount)
             VALUES (?, ?, ?, ?, "Pending Payment", ?)'
        );
        $stmt->execute([$user_id, $pickup_code, $pickup_time, $payment_method, $total]);
        $order_id = db_last_id();

        // ---- One row per item (name and price are COPIES) ---------
        $stmt = $conn->prepare(
            'INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity, line_total)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $qty      = $cart[(int)$item['item_id']];
            $unit     = (float)$item['price'];
            $line     = round($unit * $qty, 2);
            $stmt->execute([$order_id, $item['item_id'], $item['name'], $unit, $qty, $line]);
        }

        // ---- The payment record ----------------------------------
        // Online orders get a secret payment token. It is hidden
        // inside the payment QR code, so a phone can open the demo
        // payment page after scanning (see pay.php).
        $pay_status = $payment_method === 'online' ? 'pending' : 'due_at_pickup';
        $pay_token  = $payment_method === 'online' ? generate_payment_token() : null;
        $stmt = $conn->prepare(
            'INSERT INTO payments (order_id, method, amount, payment_status, payment_token, is_simulation)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$order_id, $payment_method, $total, $pay_status, $pay_token]);

        // ---- First line of the history ---------------------------
        $stmt = $conn->prepare(
            'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note)
             VALUES (?, NULL, "Pending Payment", ?, ?)'
        );
        $note = $payment_method === 'online'
            ? 'Order placed - waiting for demo payment'
            : 'Order placed - cash to be paid at the counter';
        $stmt->execute([$order_id, $user_id, $note]);

        // ---- Pop-up on every staff device -------------------------
        // The staff hears about the food order the moment it is placed.
        // For a QR order a second pop-up follows when the phone confirms
        // the payment (see change_order_status above).
        $student_name = db_value('SELECT full_name FROM users WHERE user_id = ?', [$user_id]) ?? '';
        notify_role(
            'manager',
            'New order #' . $order_id,
            short_name((string)$student_name) . ' ordered ' . money_format_balance($total)
            . ' - ' . payment_method_label($payment_method) . '.',
            $order_id
        );

        $conn->commit();

        return ['ok' => true, 'message' => 'Order placed.', 'order_id' => $order_id];

    } catch (Exception $ex) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Order creation failed: ' . $ex->getMessage());
        return ['ok' => false, 'message' => 'Your order could not be saved. Please try again.', 'order_id' => 0];
    }
}

/* ==================================================================
 * 9. SMALL PAGE HELPERS
 * ================================================================== */

/** A short friendly sentence explaining an order status to the student. */
function order_status_hint(string $status): string
{
    $hints = [
        'Pending Payment' => 'Waiting for the demo payment, or for the manager to send a cash order to the kitchen.',
        'Payment Failed'  => 'The demo payment did not go through. You can try again from the order page.',
        'Paid'            => 'Thank you! The manager will send your order to the chef very soon.',
        'Sent to Chef'    => 'Your order is in the kitchen queue, waiting for the chef to accept it.',
        'Accepted'        => 'The chef accepted your order and will start cooking shortly.',
        'Preparing'       => 'Your food is being cooked right now.',
        'Ready'           => 'Your food is ready! Show the QR code at the counter to collect it.',
        'Collected'       => 'You have collected this order. Enjoy your meal!',
        'Cancelled'       => 'This order was cancelled.',
    ];
    return $hints[$status] ?? '';
}

/** The start page of each role after logging in. */
function dashboard_for(string $role): string
{
    switch ($role) {
        case 'manager':
            return 'manager/dashboard.php';
        case 'chef':
            return 'chef/kitchen.php';
        default:
            return 'student/menu.php';
    }
}

/** Short first name for the kitchen screen, e.g. "Ali Khan" -> "Ali". */
function short_name(?string $full_name): string
{
    $parts = preg_split('/\s+/', trim((string)$full_name));
    return $parts[0] ?? '';
}

/** Print the friendly "nothing here yet" box. */
function empty_state(string $title, string $text = ''): void
{
    echo '<div class="empty-state">';
    echo '<div class="empty-icon">&#127869;</div>';
    echo '<h3>' . e($title) . '</h3>';
    if ($text !== '') {
        echo '<p>' . e($text) . '</p>';
    }
    echo '</div>';
}
