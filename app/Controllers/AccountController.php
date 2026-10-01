<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;

/**
 * Placeholder landing pages for the post-login dashboards. Feature modules
 * replace these views later.
 */
final class AccountController
{
    public function account(): void
    {
        Response::view('account/placeholder', [
            'title' => 'My Account',
            'heading' => 'My Account',
        ]);
    }

    public function dashboard(): void
    {
        $user = Auth::user() ?? [];

        Response::view('account/placeholder', [
            'title'    => 'Dashboard',
            'heading'  => 'Dashboard',
            'subtitle' => 'Signed in as ' . (string) ($user['email'] ?? 'unknown'),
        ]);
    }
}
