<?php
/**
 * logout.php  -  Log out of the website.
 * The log out button in the menu sends a POST form with the CSRF token,
 * so a hidden link on another website cannot log you out.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

// Delete everything in the session (user id, cart, tokens).
logout_user();

// Start a fresh session only to carry the goodbye message.
session_start();
flash_set('info', 'You have been logged out. See you again soon!');
redirect(url('index.php'));
