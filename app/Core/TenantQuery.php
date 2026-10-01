<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Tenant-scoped query runner. Every statement must contain the :__tenant_id
 * placeholder; it is bound to the resolved restaurant so a missing WHERE
 * clause is impossible to write by accident.
 */
final class TenantQuery
{
    public const PLACEHOLDER = '__tenant_id';

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::run($sql, $params);

        return $stmt->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::run($sql, $params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    private static function run(string $sql, array $params): \PDOStatement
    {
        $tenantId = Tenant::id();

        if ($tenantId === null) {
            throw new RuntimeException('No tenant resolved; refusing to run a tenant-scoped query.');
        }

        if (!str_contains($sql, ':' . self::PLACEHOLDER)) {
            throw new RuntimeException('Tenant query must contain the :' . self::PLACEHOLDER . ' placeholder.');
        }

        $params[self::PLACEHOLDER] = $tenantId;

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }
}
