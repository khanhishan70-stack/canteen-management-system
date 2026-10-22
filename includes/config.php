<?php
/**
 * config.php  -  Database connection and project settings.
 *
 * >>> CHANGE THESE 5 LINES IF YOUR MYSQL SETUP IS DIFFERENT <<<
 * XAMPP uses user "root" with an empty password by default.
 */
define('DB_HOST', '127.0.0.1');   // MySQL host (XAMPP default)
define('DB_PORT', 3306);          // MySQL port (XAMPP default)
define('DB_NAME', 'canteen_db');  // Database created by database/canteen_db.sql
define('DB_USER', 'root');        // MySQL user
define('DB_PASS', '');            // MySQL password (empty in XAMPP)

/* Small project settings. */
define('APP_NAME', 'Campus Canteen');
define('CURRENCY', 'Rs. ');
define('APP_TIMEZONE', 'Asia/Karachi'); // change to your country if you like

/*
 * Welcome money every visitor gets in the demo wallet.
 * Ordering food needs NO login: a plain visitor starts with this much,
 * and an account is created for them quietly at checkout time.
 */
define('DEMO_WELCOME_BALANCE', 500.00);

/*
 * Error handling:
 * Technical error details are written to the PHP error log only.
 * The visitor sees a friendly message instead of database passwords,
 * table names or file paths.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set(APP_TIMEZONE);

/*
 * Work out the base path of the project automatically.
 * Examples:
 *   project inside htdocs  -> "/canteen management system"
 *   project in htdocs root -> ""
 * This way every link keeps working whatever the folder is called.
 */
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$docRoot     = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));

if ($docRoot !== '' && stripos($projectRoot, $docRoot) === 0) {
    $basePath = substr($projectRoot, strlen($docRoot));
} else {
    $basePath = '';
}
define('BASE_PATH', rtrim($basePath, '/'));

/* ------------------------------------------------------------------
 * Create ONE database connection for the whole website (PDO).
 * All queries in this project use prepared statements, so user input
 * is never mixed into the SQL text.
 * ------------------------------------------------------------------ */
try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $conn = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
    ]);
} catch (PDOException $ex) {
    error_log('Database connection failed: ' . $ex->getMessage());
    http_response_code(500);
    exit(
        '<h1>Database not ready</h1>' .
        '<p>Could not connect to MySQL. Please start <b>Apache</b> and <b>MySQL</b> in the ' .
        'XAMPP Control Panel and import <b>database/canteen_db.sql</b> ' .
        '(see README.md, step 4).</p>'
    );
}

/* Any other unexpected error gets a friendly page, never a stack trace. */
set_exception_handler(function (Throwable $error) {
    error_log('Unhandled error: ' . $error->getMessage());
    http_response_code(500);
    echo '<h1>Sorry, something went wrong</h1>'
       . '<p>The problem was written to the error log. '
       . 'Please go back and try again.</p>';
    exit;
});
