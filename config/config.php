<?php
// Dineqor configuration
define('APP_NAME', 'Dineqor');
define('APP_ENV', 'production');
define('BASE_URL', '');
define('APP_ORIGIN', 'https://dineqor.kesug.com');
define('MAIL_FROM', 'no-reply@localhost'); // Configure a valid sender and mail service before testing email features.
define('DB_HOST', 'sql213.infinityfree.com');
define('DB_NAME', 'if0_43057787_dineqor');
define('DB_USER', 'if0_43057787');
define('DB_PASS', 'Dineqor2026');
define('UPLOAD_DIR', __DIR__ . '/../uploads/restaurants/');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCK_SECONDS', 900);

date_default_timezone_set('Africa/Nairobi');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
ini_set('log_errors', '1');