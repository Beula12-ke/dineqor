<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env parser. No external dependencies.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $items = [];

    public static function load(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');

            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));

            if ($key === '') {
                continue;
            }

            $value = self::clean(substr($line, $pos + 1));

            self::$items[$key] = $value;
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items[$key] ?? $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null ? $default : (int) $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$items);
    }

    /**
     * Trim the raw value and strip a single layer of wrapping quotes.
     */
    private static function clean(string $value): string
    {
        $value = trim($value);
        $len = strlen($value);

        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        return $value;
    }
}
