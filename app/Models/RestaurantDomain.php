<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class RestaurantDomain
{
    public static function findByDomain(string $domain): ?array
    {
        $sql = 'SELECT * FROM restaurant_domains WHERE domain = :domain LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['domain' => strtolower(trim($domain))]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function allForRestaurant(int $restaurantId): array
    {
        $sql = 'SELECT * FROM restaurant_domains WHERE restaurant_id = :rid ORDER BY is_primary DESC, id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['rid' => $restaurantId]);

        return $stmt->fetchAll();
    }
}
