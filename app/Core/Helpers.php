<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;

if (!function_exists('e')) {
    function e(mixed $string): string
    {
        return htmlspecialchars((string) $string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): string
    {
        $old = Session::getFlash('__old');
        $value = is_array($old) ? ($old[$key] ?? $default) : $default;

        return e($value);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): never
    {
        App\Core\Response::redirect($url);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): void
    {
        $relative = str_replace('..', '', $template);
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

        $file = APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views'
            . DIRECTORY_SEPARATOR . $relative . '.php';

        if (!is_readable($file)) {
            throw new RuntimeException("View [{$template}] not found.");
        }

        extract($data, EXTR_SKIP);
        require $file;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = '/' . ltrim($path, '/');

        return $path === '/' ? APP_URL . '/' : APP_URL . $path;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        return APP_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('is_active')) {
    function is_active(string $path): bool
    {
        $current = Request::uri();
        $path = '/' . trim($path, '/');

        return $current === $path;
    }
}

if (!function_exists('money')) {
    function money(float|int|string $amount, string $currency = 'KES'): string
    {
        return $currency . ' ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('flash_message')) {
    function flash_message(string $type = 'error'): ?string
    {
        $value = Session::getFlash($type);

        return is_string($value) ? $value : null;
    }
}

if (!function_exists('validate_csrf')) {
    function validate_csrf(): void
    {
        Csrf::check();
    }
}
