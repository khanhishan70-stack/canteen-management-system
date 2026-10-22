<?php
/**
 * login.php  -  Old shared login page.
 *
 * The website now has three separate systems (student, staff, chef),
 * each with its own login page. This file only redirects to the home
 * page so that old links still work.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Go straight to your own system.
if (is_logged_in()) {
    redirect(url(dashboard_for(current_user()['role'])));
}

redirect(url('index.php'));
