<?php
/**
 * register.php  -  A new student account.
 * Only students register themselves; manager and chef accounts are
 * created by the teacher / in the database file.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(url(dashboard_for(current_user()['role'])));
}

$errors = [];
$old    = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['full_name'] = post_str('full_name', 100);
    $old['email']     = post_str('email', 150);
    $old['phone']     = post_str('phone', 20);
    $password         = (string)($_POST['password'] ?? '');
    $confirm          = (string)($_POST['confirm_password'] ?? '');

    // ---- Server side checks (the browser check alone is not enough) ----
    if (mb_strlen($old['full_name']) < 3) {
        $errors[] = 'Please write your full name (at least 3 letters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please write a valid email address.';
    }
    if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $old['phone'])) {
        $errors[] = 'Phone number may only contain digits, spaces, + and -.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Your password must be at least 8 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords are not the same.';
    }

    if (!$errors) {
        // Is this email already used?
        $exists = db_row('SELECT user_id FROM users WHERE email = ?', [$old['email']]);

        if ($exists) {
            $errors[] = 'An account with this email already exists. Please log in instead.';
        } else {
            // The password is stored as a HASH - the plain text is never saved.
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = db_run(
                'INSERT INTO users (full_name, email, phone, password_hash, role, balance)
                 VALUES (?, ?, ?, ?, "student", 500.00)',
                [$old['full_name'], $old['email'], $old['phone'] ?: null, $hash]
            );

            login_user(db_last_id());
            flash_set('success', 'Your account is ready. Rs. 500.00 demo money has been added to your wallet!');
            redirect(url('student/menu.php'));
        }
    }
}

$page_title = 'Register';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
  <div class="card">
    <h1>Create a student account</h1>
    <p class="muted">Register once, then order from any device.</p>

    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" data-submit-once>
      <?= csrf_field() ?>

      <label for="full_name">Full name</label>
      <input type="text" id="full_name" name="full_name" maxlength="100"
             value="<?= e($old['full_name']) ?>" placeholder="e.g. Ali Khan" required>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" maxlength="150"
             value="<?= e($old['email']) ?>" placeholder="you@example.com" required>

      <label for="phone">Phone (optional)</label>
      <input type="text" id="phone" name="phone" maxlength="20"
             value="<?= e($old['phone']) ?>" placeholder="0300 1234567">

      <label for="password">Password</label>
      <input type="password" id="password" name="password" minlength="8" required>
      <p class="field-hint">At least 8 characters.</p>

      <label for="confirm_password">Repeat password</label>
      <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

      <div class="form-actions">
        <button class="btn btn-primary btn-block" type="submit">Create account</button>
      </div>
    </form>

    <p class="auth-links">
      Already registered? <a href="<?= e(url('student/login.php')) ?>">Log in</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
