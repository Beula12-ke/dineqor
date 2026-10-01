<?php
// Public directory: GET ?q=&cuisine=&open=1  ->  { restaurants[], cuisines[], total }
require_once __DIR__ . '/../../includes/tenant.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('Method not allowed', 405);

$q        = clean_str($_GET['q'] ?? '', 80);
$cuisine  = clean_str($_GET['cuisine'] ?? '', 80);
$openOnly = ($_GET['open'] ?? '') === '1';

$sql = "SELECT id, slug, name, cuisine, city, logo_path, cover_path, delivery_enabled, pickup_enabled,
               price_range, primary_color,
               (SELECT ROUND(AVG(rv.rating),1) FROM reviews rv WHERE rv.restaurant_id=restaurants.id AND rv.is_approved=1) AS rating_average,
               (SELECT COUNT(*) FROM reviews rv WHERE rv.restaurant_id=restaurants.id AND rv.is_approved=1) AS review_count
        FROM restaurants WHERE status = 'active' AND deleted_at IS NULL";
$args = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR cuisine LIKE ? OR city LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($args, $like, $like, $like);
}
if ($cuisine !== '') { $sql .= ' AND cuisine = ?'; $args[] = $cuisine; }
$sql .= ' ORDER BY approved_at DESC, name LIMIT 60';

$st = db()->prepare($sql);
$st->execute($args);
$rows  = $st->fetchAll();
$hours = hours_by_restaurant(array_map(fn($r) => (int)$r['id'], $rows));

$list = [];
foreach ($rows as $r) {
    $s = open_status($hours[(int)$r['id']] ?? []);
    if ($openOnly && $s['open'] !== true) continue;
    $list[] = [
        'slug'     => $r['slug'],
        'name'     => $r['name'],
        'cuisine'  => $r['cuisine'],
        'city'     => $r['city'],
        'cover'    => public_url($r['cover_path']),
        'logo'     => public_url($r['logo_path']),
        'delivery' => (bool)$r['delivery_enabled'],
        'pickup'   => (bool)$r['pickup_enabled'],
        'price'    => $r['price_range'] !== null ? max(1, min(4, (int)$r['price_range'])) : null,
        'rating'   => $r['rating_average'] !== null ? (float)$r['rating_average'] : null,
        'review_count' => (int)$r['review_count'],
        'color'    => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['primary_color']) ? $r['primary_color'] : null,
        'open'     => $s['open'],
        'until'    => $s['until'],
        'url'      => BASE_URL . '/restaurant/' . $r['slug'],
    ];
}

$cuisines = db()->query("SELECT DISTINCT cuisine FROM restaurants
    WHERE status = 'active' AND cuisine IS NOT NULL AND cuisine <> '' AND deleted_at IS NULL
    ORDER BY cuisine")->fetchAll(PDO::FETCH_COLUMN);

json_out(['ok' => true, 'total' => count($list), 'restaurants' => $list, 'cuisines' => $cuisines]);
