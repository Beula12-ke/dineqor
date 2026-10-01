<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Tenant;
use App\Models\Restaurant;

final class StorefrontController
{
    public function show(string $slug = ''): void
    {
        $tenant = Tenant::get();

        if ($tenant === null && $slug !== '') {
            $tenant = Restaurant::findBySlug($slug);
        }

        if ($tenant === null) {
            Response::view('errors/404', ['title' => 'Restaurant Not Found'], 404);

            return;
        }

        Response::view('storefront/show', [
            'title'      => (string) $tenant['name'],
            'restaurant' => $tenant,
        ]);
    }
}
