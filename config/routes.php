<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\StorefrontController;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/** @var Router $router */

$router->get('/', static function (): void {
    Response::view('home', ['title' => 'Home']);
});

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

// Registered before the {slug} catch-all so dashboard paths win.
$router->get(
    '/admin/dashboard',
    [AdminController::class, 'dashboard'],
    [AuthMiddleware::class, [RoleMiddleware::class]]
);

$router->get(
    '/restaurant/dashboard',
    [AccountController::class, 'dashboard'],
    [AuthMiddleware::class, [RoleMiddleware::class, 'view_orders']]
);

$router->get(
    '/staff/dashboard',
    [AccountController::class, 'dashboard'],
    [AuthMiddleware::class, [RoleMiddleware::class, 'view_orders']]
);

$router->get(
    '/customer/account',
    [AccountController::class, 'account'],
    [AuthMiddleware::class]
);

$router->get('/restaurant/{slug}', [StorefrontController::class, 'show']);
