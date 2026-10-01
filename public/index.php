<?php

/**
 * Dineqor front controller.
 */

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\Tenant;

define('APP_ROOT', dirname(__DIR__));

// Composer autoloader when installed; otherwise a minimal PSR-4 fallback so the
// app still boots on a fresh checkout.
$composerAutoload = APP_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (is_readable($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        $base = APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $base . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

        if (is_readable($file)) {
            require $file;
        }
    });
}

require APP_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Helpers.php';

Session::start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 0');

Tenant::resolve();

try {
    $router = new Router();
    require APP_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.php';
    $router->dispatch(Request::method(), Request::uri());
} catch (Throwable $e) {
    error_log('[Dineqor] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (config('app.debug', false)) {
        http_response_code(500);
        echo '<pre style="padding:2rem;font:14px/1.5 ui-monospace,monospace;color:#b91c1c;white-space:pre-wrap">';
        echo e($e->getMessage()) . "\n\n" . e($e->getTraceAsString());
        echo '</pre>';
        exit;
    }

    Response::view('errors/404', ['title' => 'Something Went Wrong'], 500);
}
