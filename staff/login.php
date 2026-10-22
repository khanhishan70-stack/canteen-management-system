<?php
/**
 * staff/login.php  -  STAFF SYSTEM login.
 * The canteen staff see every order students place, manage the menu,
 * send orders to the kitchen and verify QR pickups here.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$login_role   = 'manager';
$system_short = 'staff';
$system_name  = 'Staff System';
$system_blurb = 'See the orders students place, manage the menu and hand over food.';
$system_icon  = '&#128188;';

require __DIR__ . '/../includes/login_page.php';
