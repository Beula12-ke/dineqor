<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        if (!preg_match('#^https?://#i', $url)) {
            $url = url($url);
        }

        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function view(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');

        $relative = str_replace('..', '', $template);
        $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

        $file = APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views'
            . DIRECTORY_SEPARATOR . $relative . '.php';

        if (!is_readable($file)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
            exit;
        }

        extract($data, EXTR_SKIP);
        require $file;
        exit;
    }

    public static function text(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    public static function abort(int $status = 404, string $view = 'errors/404', array $data = []): never
    {
        http_response_code($status);
        self::view($view, $data, $status);
        exit;
    }
}
