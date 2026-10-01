<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Env.php';

use App\Core\Env;

$root = dirname(__DIR__);
Env::load($root . '/.env');
Env::load($root . '/.env.local');   // gitignored per-environment overrides

define('APP_NAME', (string) Env::get('APP_NAME', 'Dineqor'));
define('APP_ENV', (string) Env::get('APP_ENV', 'local'));

// BASE_URL is a PATH prefix, not a URL: url() returns BASE_URL . '/' . $path and the value is
// also handed to JS as window.BASE. Deriving it from APP_URL's path keeps a subfolder install
// ('/dineqor') and a root install ('') both correct. APP_BASE_PATH overrides when needed.
$basePath = (string) Env::get('APP_BASE_PATH', '');
if ($basePath === '') {
    $parsed = parse_url((string) Env::get('APP_URL', ''), PHP_URL_PATH);
    $basePath = is_string($parsed) ? rtrim($parsed, '/') : '';
}
define('BASE_URL', $basePath);

define('APP_ORIGIN', rtrim((string) Env::get('APP_ORIGIN', (string) Env::get('APP_URL', '')), '/'));
define('MAIL_FROM', (string) Env::get('MAIL_FROM', 'no-reply@localhost'));

define('DB_HOST', (string) Env::get('DB_HOST', '127.0.0.1'));
define('DB_PORT', (string) Env::get('DB_PORT', '3306'));
define('DB_NAME', (string) Env::get('DB_NAME', 'dineqor'));
define('DB_USER', (string) Env::get('DB_USER', 'root'));
define('DB_PASS', (string) Env::get('DB_PASS', ''));

define('UPLOAD_DIR', __DIR__ . '/../uploads/restaurants/');

define('MAX_LOGIN_ATTEMPTS', Env::int('MAX_LOGIN_ATTEMPTS', 5));
define('LOGIN_LOCK_SECONDS', Env::int('LOGIN_LOCK_SECONDS', 900));

date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'Africa/Nairobi'));

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
ini_set('log_errors', '1');