<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Permissions;
use App\Core\Response;

final class AdminController
{
    public function dashboard(): void
    {
        // Platform-only surface: tenant users never reach this screen.
        if (!Auth::isPlatformAdmin()) {
            http_response_code(403);
            Response::view('errors/403', ['title' => 'Access Denied'], 403);

            return;
        }

        Permissions::require('admin.dashboard.view');

        Response::view('admin/dashboard', ['title' => 'Platform Dashboard']);
    }
}
