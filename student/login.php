<?php
/**
 * student/login.php  -  STUDENT SYSTEM login.
 * Students browse the menu, place orders and pay here.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$login_role   = 'student';
$system_short = 'student';
$system_name  = 'Student System';
$system_blurb = 'Browse the menu, place an order and pay from your laptop or phone.';
$system_icon  = '&#127860;';

require __DIR__ . '/../includes/login_page.php';
