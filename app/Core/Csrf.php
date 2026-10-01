<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();

        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(string $token): bool
    {
        $stored = Session::get(self::SESSION_KEY);

        if (!is_string($stored) || $stored === '' || $token === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    public static function check(): void
    {
        $token = (string) (Request::input('_token') ?? Request::header('X-CSRF-Token') ?? '');

        if (self::verify($token)) {
            return;
        }

        self::fail();
    }

    private static function fail(): never
    {
        if (Request::isJson() || str_contains((string) Request::header('Accept'), 'application/json')) {
            Response::json(['error' => 'CSRF token mismatch.'], 419);
        }

        http_response_code(419);
        Response::view('errors/419', ['title' => 'Page Expired'], 419);
        exit;
    }
}
