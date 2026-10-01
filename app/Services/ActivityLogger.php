<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Tenant;
use Throwable;

final class ActivityLogger
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        array $meta = []
    ): void {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO activity_logs
                (user_id, restaurant_id, action, entity_type, entity_id, description, meta_json, ip_address, user_agent, created_at)
             VALUES
                (:user_id, :restaurant_id, :action, :entity_type, :entity_id, :description, :meta_json, :ip_address, :user_agent, NOW())'
        );

        $packedIp = @inet_pton(Request::ip());

        try {
            $stmt->execute([
                'user_id'       => Auth::id(),
                'restaurant_id' => Tenant::id(),
                'action'        => mb_substr($action, 0, 100),
                'entity_type'   => $entityType !== null ? mb_substr($entityType, 0, 50) : null,
                'entity_id'     => $entityId,
                'description'   => $description,
                'meta_json'     => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip_address'    => $packedIp === false ? null : $packedIp,
                'user_agent'    => Request::userAgent(),
            ]);
        } catch (Throwable $e) {
            // Logging must never break the request.
            error_log('[ActivityLogger] ' . $e->getMessage());
        }
    }
}
