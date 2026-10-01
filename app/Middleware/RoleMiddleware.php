<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Permissions;
use App\Core\Response;

/**
 * Route-level guard: authenticates the user and checks the given permission
 * codes against the current tenant.
 */
final class RoleMiddleware
{
    /** @var array<int,string> */
    private array $required = [];

    public function __construct(string ...$permissions)
    {
        $this->required = $permissions;
    }

    public function handle(): void
    {
        Auth::require();

        if (Auth::isPlatformAdmin()) {
            return;
        }

        if ($this->required === []) {
            return;
        }

        foreach ($this->required as $permission) {
            if (Permissions::userCan($permission)) {
                return;
            }
        }

        http_response_code(403);
        Response::view('errors/403', ['title' => 'Access Denied'], 403);
        exit;
    }
}
