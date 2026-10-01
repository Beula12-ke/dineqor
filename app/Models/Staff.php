<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Staff
{
    public static function findByUserAndRestaurant(int $userId, int $restaurantId): ?array
    {
        $sql = 'SELECT * FROM staff
                 WHERE user_id = :uid AND restaurant_id = :rid AND status = :status
                 LIMIT 1';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            'uid'    => $userId,
            'rid'    => $restaurantId,
            'status' => 'active',
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function allForRestaurant(int $restaurantId): array
    {
        $sql = 'SELECT s.*, u.email, u.full_name
                  FROM staff s
                  JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
                 WHERE s.restaurant_id = :rid
                 ORDER BY s.id ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['rid' => $restaurantId]);

        return $stmt->fetchAll();
    }
}
