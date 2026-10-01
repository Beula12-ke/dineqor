<?php
require_once __DIR__ . '/security.php';

// Public storefront tenant resolution: by slug or by domain. Only ACTIVE restaurants are public.
function resolve_public_restaurant(?string $slug = null, ?string $host = null): ?array {
    if ($slug) {
        $st = db()->prepare("SELECT * FROM restaurants WHERE slug = ? AND status = 'active' AND deleted_at IS NULL");
        $st->execute([$slug]);
    } elseif ($host) {
        $st = db()->prepare("SELECT r.* FROM restaurants r JOIN restaurant_domains d ON d.restaurant_id = r.id
                             WHERE d.domain = ? AND r.status = 'active' AND r.deleted_at IS NULL");
        $st->execute([strtolower($host)]);
    } else return null;
    return $st->fetch() ?: null;
}

// Is a restaurant open right now? $rows = its restaurant_hours rows. Handles closing after midnight.
// Returns ['open' => bool, 'until' => 'HH:MM'|null]. No hours on file => open = null (unknown).
function open_status(array $rows, ?DateTimeImmutable $now = null): array {
    if (!$rows) return ['open' => null, 'until' => null];
    $now = $now ?? new DateTimeImmutable('now');
    $today = (int)$now->format('w');
    $yesterday = ($today + 6) % 7;
    $t = $now->format('H:i:s');
    foreach ($rows as $h) {
        if ($h['is_closed'] || $h['open_time'] === null || $h['close_time'] === null) continue;
        $wd = (int)$h['weekday'];
        $o = $h['open_time']; $c = $h['close_time'];
        if ($wd === $today) {
            if ($c > $o && $t >= $o && $t < $c) return ['open' => true, 'until' => substr($c, 0, 5)];
            if ($c <= $o && $t >= $o) return ['open' => true, 'until' => substr($c, 0, 5)];   // runs past midnight
        }
        if ($wd === $yesterday && $c <= $o && $t < $c) return ['open' => true, 'until' => substr($c, 0, 5)];
    }
    return ['open' => false, 'until' => null];
}

// Hours for many restaurants in one query, grouped by restaurant_id.
function hours_by_restaurant(array $ids): array {
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT restaurant_id, weekday, open_time, close_time, is_closed
                         FROM restaurant_hours WHERE restaurant_id IN ($in) ORDER BY weekday, open_time");
    $st->execute(array_values($ids));
    $out = [];
    foreach ($st->fetchAll() as $h) $out[(int)$h['restaurant_id']][] = $h;
    return $out;
}
