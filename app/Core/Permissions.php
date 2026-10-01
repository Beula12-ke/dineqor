<?php

declare(strict_types=1);

namespace App\Core;

final class Permissions
{
    /** @var array<string,bool> */
    private static array $cache = [];

    public static function userCan(string $permission): bool
    {
        if (Auth::isPlatformAdmin()) {
            return true;
        }

        if (self::$cache === []) {
            self::prime();
        }

        return self::$cache[$permission] ?? false;
    }

    public static function require(string $permission): void
    {
        if (self::userCan($permission)) {
            return;
        }

        http_response_code(403);
        Response::view('errors/403', ['title' => 'Access Denied'], 403);
        exit;
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /**
     * Load the permission codes granted to the current user's staff role,
     * scoped to the active tenant.
     */
    private static function prime(): void
    {
        self::$cache = ['*' => false];

        $userId = Auth::id();
        $tenantId = Tenant::id();

        if ($userId === null || $tenantId === null) {
            return;
        }

        $sql = 'SELECT DISTINCT p.code
                  FROM staff s
                  JOIN role_permissions rp ON rp.role = s.role
                  JOIN permissions p ON p.id = rp.permission_id
                 WHERE s.user_id = :uid
                   AND s.restaurant_id = :__tenant_id
                   AND s.status = :status';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            'uid'          => $userId,
            '__tenant_id'  => $tenantId,
            'status'       => 'active',
        ]);

        foreach ($stmt->fetchAll() as $row) {
            self::$cache[(string) $row['code']] = true;
        }
    }
}
