<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Restaurant;
use App\Models\RestaurantDomain;

/**
 * Resolves the active restaurant for the current request.
 * Order: custom domain -> subdomain -> URL path -> authenticated session.
 */
final class Tenant
{
    private static ?array $tenant = null;
    private static bool $resolved = false;

    public static function resolve(): ?array
    {
        if (self::$resolved) {
            return self::$tenant;
        }

        self::$resolved = true;
        self::$tenant = self::fromDomain()
            ?? self::fromSubdomain()
            ?? self::fromPath()
            ?? self::fromSession();

        return self::$tenant;
    }

    public static function id(): ?int
    {
        $tenant = self::resolve();

        return $tenant === null ? null : (int) $tenant['id'];
    }

    public static function get(): ?array
    {
        return self::resolve();
    }

    public static function require(): array
    {
        $tenant = self::resolve();

        if ($tenant === null) {
            Response::view('errors/404', ['title' => 'Restaurant Not Found'], 404);
            exit;
        }

        return $tenant;
    }

    public static function forget(): void
    {
        self::$tenant = null;
        self::$resolved = false;
    }

    private static function fromDomain(): ?array
    {
        $host = self::host();

        if ($host === '') {
            return null;
        }

        $domain = RestaurantDomain::findByDomain($host);

        if ($domain === null) {
            return null;
        }

        $restaurant = Restaurant::find((int) $domain['restaurant_id']);

        return self::validate($restaurant);
    }

    private static function fromSubdomain(): ?array
    {
        $host = self::host();

        if ($host === '') {
            return null;
        }

        $baseDomain = (string) config('app.base_domain', '');

        if ($baseDomain === '' || !str_ends_with($host, '.' . $baseDomain)) {
            return null;
        }

        $slug = substr($host, 0, -1 * (strlen($baseDomain) + 1));

        if ($slug === '' || str_contains($slug, '.')) {
            return null;
        }

        return self::validate(Restaurant::findBySlug($slug));
    }

    private static function fromPath(): ?array
    {
        if (!preg_match('#^/restaurant/([^/]+)#', Request::uri(), $m)) {
            return null;
        }

        return self::validate(Restaurant::findBySlug($m[1]));
    }

    private static function fromSession(): ?array
    {
        $restaurantId = Auth::restaurantId();

        if ($restaurantId === null) {
            return null;
        }

        return self::validate(Restaurant::find($restaurantId));
    }

    private static function validate(?array $restaurant): ?array
    {
        if ($restaurant === null) {
            return null;
        }

        if (($restaurant['status'] ?? '') !== 'active') {
            return null;
        }

        return $restaurant;
    }

    private static function host(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $host = explode(':', $host)[0];

        return strtolower(trim($host));
    }
}
