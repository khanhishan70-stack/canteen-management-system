<?php
/**
 * includes/login_page.php  -  The login screen shared by the three systems.
 *
 * The website is split into three separate systems:
 *
 *   STUDENT SYSTEM  (student/login.php)  - order food and pay
 *   STAFF SYSTEM    (staff/login.php)    - see and manage the orders
 *   CHEF SYSTEM     (chef/login.php)     - cook the orders
 *
 * Each system has its OWN login page, and each login only accepts
 * its own role. A chef password typed on the staff login is refused
 * with a friendly message that points to the right system.
 *
 * Before including this file, the login page sets:
 *   $login_role   - 'student', 'manager' or 'chef'
 *   $system_name  - e.g. 'Student System'
 *   $system_short - e.g. 'student'
 *   $system_blurb - one line explaining what this system does
 *   $system_icon  - a small emoji entity, e.g. '&#127860;'
 */

/* Already logged in? Go straight to your own system. */
if (is_logged_in()) {
    redirect(url(dashboard_for(current_user()['role'])));
}

/* The three systems, used for the "you are in the wrong place" hint. */
$portals = [
    'student' => ['Student System', 'student/',  '&#127860;'],
    'manager' => ['Staff System',   'staff/',    '&#128188;'],
    'chef'    => ['Chef System',    'chef/',     '&#127859;'],
];

$errors     = [];
$email      = '';
$wrong_role = null;   // set when the password is right but the system is wrong

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = post_str('email', 150);
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter your email address.';
    }
    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (!$errors) {
        /* Find the account. Passwords are only checked with password_verify(). */
        $user = db_row(
            'SELECT user_id, password_hash, role FROM users WHERE email = ? AND is_active = 1',
            [$email]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['role'] !== $login_role) {
                /* Right password, wrong system: send them to their own login. */
                $wrong_role = $user['role'];
            } else {
                login_user((int)$user['user_id']);
                flash_set('success', 'Welcome back!');
                redirect(url(dashboard_for($user['role'])));
            }
        } else {
            /* One message for both cases: we never say which part was wrong. */
            $errors[] = 'Email or password is incorrect.';
        }
    }
}

$page_title = $system_name . ' - Log in';
require __DIR__ . '/header.php';
?>

<div class="auth-page portal-<?= e($system_short) ?>">
  <div class="card">
    <p class="portal-tag"><?= e($system_icon) ?> <?= e($system_name) ?></p>
    <h1>Log in</h1>
    <p class="muted"><?= e($system_blurb) ?></p>
    <p class="muted small">
      <a href="<?= e(url($portals[$login_role][1])) ?>">&larr; What can I do on this system?</a>
    </p>

    <?php foreach ($errors as $error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <?php if ($wrong_role !== null && isset($portals[$wrong_role])): ?>
      <div class="alert alert-warning">
        That account belongs to the <strong><?= e($portals[$wrong_role][0]) ?></strong>.
        Please log in on that system instead.
        <a href="<?= e(url($portals[$wrong_role][1])) ?>">Go to the <?= e($portals[$wrong_role][0]) ?> &rarr;</a>
      </div>
    <?php endif; ?>

    <form method="post" data-submit-once>
      <?= csrf_field() ?>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>"
             placeholder="you@example.com" required>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Your password" required>

      <div class="form-actions">
        <button class="btn btn-primary btn-block" type="submit">Log in to the <?= e($system_short) ?> system</button>
      </div>
    </form>

    <?php if ($login_role === 'student'): ?>
      <p class="auth-links">
        New student? <a href="<?= e(url('register.php')) ?>">Create an account</a>
      </p>
    <?php endif; ?>

    <p class="auth-links muted small">
      Other systems:
      <?php foreach ($portals as $role => $p): ?>
        <?php if ($role !== $login_role): ?>
          <a href="<?= e(url($p[1])) ?>"><?= e($p[0]) ?></a> &middot;
        <?php endif; ?>
      <?php endforeach; ?>
      <a href="<?= e(url('index.php')) ?>">Home</a>
    </p>

    <p class="auth-links muted small">
      Demo logins are listed in README.md &mdash; e.g. <code>ali@student.local</code>
    </p>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
