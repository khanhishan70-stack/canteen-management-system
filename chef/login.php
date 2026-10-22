<?php
/**
 * chef/login.php  -  CHEF SYSTEM login.
 * The chef sees the incoming orders on a big phone-friendly screen
 * and moves them: Accept -> Preparing -> Ready.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$login_role   = 'chef';
$system_short = 'chef';
$system_name  = 'Chef System';
$system_blurb = 'The kitchen screen: see what to cook and update each order.';
$system_icon  = '&#127859;';

require __DIR__ . '/../includes/login_page.php';
