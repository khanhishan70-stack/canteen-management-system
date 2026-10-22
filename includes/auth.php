<?php
/**
 * auth.php  -  Who is logged in, and what are they allowed to see?
 * Every page includes this file, so the same rules apply everywhere.
 */

/* Start one safe session (cookie is not readable from JavaScript). */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('canteenSession');
    session_start();
}

/** Remember who just logged in (never store the password). */
function login_user(int $user_id): void
{
    session_regenerate_id(true); // new session id = safer
    $_SESSION['user_id'] = $user_id;
}

/** Forget everything about the current user. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * The logged-in user, read fresh from the database on every page.
 * We never trust anything that was stored in the session except the id.
 * Returns null when nobody is logged in.
 *
 * The result is remembered only for as long as the SAME id stays in the
 * session - a silent guest sign-in (see ensure_guest_account) changes
 * the id, and the next call must see the new account.
 */
function current_user(): ?array
{
    static $cached_id = -1;   // -1 = "never asked"
    static $user      = null;

    $user_id = (int)($_SESSION['user_id'] ?? 0);

    if ($user_id === $cached_id) {
        return $user;
    }
    $cached_id = $user_id;

    if ($user_id <= 0) {
        $user = null;
        return null;
    }

    $user = db_row(
        'SELECT user_id, full_name, email, role, balance FROM users WHERE user_id = ? AND is_active = 1',
        [$user_id]
    );
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Every staff / student page starts with this line. */
function require_login(): array
{
    /*
     * A guest who ordered earlier returns to the site: quietly give them
     * their demo account back. They never see a login form, so ordering
     * stays login-free even after closing the browser.
     */
    if (empty($_SESSION['user_id']) && !empty($_SESSION['guest_user_id'])) {
        $guest_id = (int)$_SESSION['guest_user_id'];
        if (db_value('SELECT user_id FROM users WHERE user_id = ? AND is_active = 1', [$guest_id])) {
            $_SESSION['user_id'] = $guest_id;   // silent sign-in
        } else {
            unset($_SESSION['guest_user_id']);
        }
    }

    $user = current_user();
    if ($user === null) {
        flash_set('info', 'Please log in first. Choose your system on the home page.');
        redirect(url('index.php'));
    }
    return $user;
}

/**
 * Only allow one specific role, e.g. require_role('manager').
 * A student who opens a staff page gets a friendly "not allowed" page
 * with a button that leads to their own system.
 */
function require_role(string $role): array
{
    $user = require_login();

    if ($user['role'] !== $role) {
        // Friendly names + where each role should go instead.
        $systems = [
            'student' => ['Student System', '&#127860;', 'student/menu.php'],
            'manager' => ['Staff System',   '&#128188;', 'manager/dashboard.php'],
            'chef'    => ['Chef System',    '&#127859;', 'chef/kitchen.php'],
        ];
        $mine   = $systems[$user['role']] ?? null;
        $target = $systems[$role]       ?? null;

        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Not allowed</title>'
           . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head>'
           . '<body class="center-page"><div class="card narrow">'
           . '<h1>&#128683; This page is not for you</h1>'
           . '<p>This page belongs to the <b>' . e($target[0] ?? $role) . '</b>. '
           . 'You are logged in on the <b>' . e($mine[0] ?? $user['role']) . '</b>.</p>'
           . '<p><a class="btn btn-primary" href="' . e(url($mine[2] ?? 'index.php')) . '">'
           . 'Go to my system ' . e($mine[1] ?? '') . '</a> ';
        if ($target !== null) {
            echo '<a class="btn btn-muted" href="' . e(url($target[2])) . '">'
               . 'Open the ' . e($target[0]) . '</a>';
        }
        echo '</p></div></body></html>';
        exit;
    }
    return $user;
}

/* ==================================================================
 * CSRF PROTECTION
 * A secret token is added to every form. On submit the server checks
 * it, so another website cannot post commands using your session.
 * ================================================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Print the hidden CSRF field to place inside a <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stop the request when the token is missing or wrong. */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        echo '<h1>Session expired</h1>'
           . '<p>Please go back, refresh the page and try again.</p>';
        exit;
    }
}
