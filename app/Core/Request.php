<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Read-only access to the incoming request. Never trust these values for
 * authorization decisions (see Permissions / TenantQuery).
 */
final class Request
{
    private static ?array $json = null;

    public static function input(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function all(): array
    {
        $data = array_merge($_GET, $_POST, self::json());

        return array_map(static fn ($value) => is_string($value) ? self::sanitize($value) : $value, $data);
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = (string) parse_url($uri, PHP_URL_PATH);
        $uri = '/' . trim((string) $uri, '/');

        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    public static function ip(): string
    {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            $value = $_SERVER[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $first = trim(explode(',', $value)[0]);

                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }

        return '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function isJson(): bool
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';

        return str_contains(strtolower((string) $type), 'application/json');
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static fn ($item) => self::sanitize($item), $value);
        }

        if (!is_string($value)) {
            return $value;
        }

        // Trim control characters; HTML escaping happens at output via e().
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        return trim($value);
    }

    private static function json(): array
    {
        if (self::$json !== null) {
            return self::$json;
        }

        self::$json = [];

        if (self::isJson()) {
            $raw = file_get_contents('php://input');

            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);

                if (is_array($decoded)) {
                    self::$json = $decoded;
                }
            }
        }

        return self::$json;
    }
}
