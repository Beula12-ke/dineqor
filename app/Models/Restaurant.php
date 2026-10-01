<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Restaurant
{
    public static function find(int $id): ?array
    {
        $sql = 'SELECT * FROM restaurants WHERE id = :id AND deleted_at IS NULL LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function findBySlug(string $slug): ?array
    {
        $sql = 'SELECT * FROM restaurants WHERE slug = :slug AND deleted_at IS NULL LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['slug' => strtolower(trim($slug))]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function findByDomain(string $domain): ?array
    {
        $domain = strtolower(trim($domain));
        $sql = 'SELECT restaurant_id FROM restaurant_domains WHERE domain = :domain LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['domain' => $domain]);
        $restaurantId = $stmt->fetchColumn();

        return $restaurantId === false ? null : self::find((int) $restaurantId);
    }

    public static function findByOwner(int $ownerUserId): ?array
    {
        $sql = 'SELECT * FROM restaurants WHERE owner_user_id = :uid AND deleted_at IS NULL ORDER BY id ASC LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['uid' => $ownerUserId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array{status?:string, search?:string, limit?:int, offset?:int} $filters
     */
    public static function all(array $filters = []): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = (string) $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :search OR slug LIKE :search OR email LIKE :search)';
            $params['search'] = '%' . (string) $filters['search'] . '%';
        }

        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 50;
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = 'SELECT * FROM restaurants WHERE ' . implode(' AND ', $where)
            . ' ORDER BY name ASC LIMIT :limit OFFSET :offset';

        $stmt = Database::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
