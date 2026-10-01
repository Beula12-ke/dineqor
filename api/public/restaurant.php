<?php
// One restaurant's storefront data: GET ?slug=  ->  { restaurant, menu[], hours[] }
require_once __DIR__ . '/../../includes/tenant.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mpesa.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('Method not allowed', 405);

$slug = clean_str($_GET['slug'] ?? '', 80);
$r = resolve_public_restaurant($slug ?: null);
if (!$r) json_error('Restaurant not found.', 404);
$rid = (int)$r['id'];                       // tenant id from the DB row, never from the browser

$st = db()->prepare('SELECT id, name FROM categories
                     WHERE restaurant_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY sort_order, name');
$st->execute([$rid]);
$cats = $st->fetchAll();

$st = db()->prepare('SELECT id, category_id, name, description, price, image_path, is_featured FROM products
                     WHERE restaurant_id = ? AND is_active = 1 AND is_available = 1 AND deleted_at IS NULL
                     ORDER BY sort_order, name LIMIT 300');
$st->execute([$rid]);
$prodRows = $st->fetchAll();
$pids = array_map(fn($p) => (int)$p['id'], $prodRows);
$vars = $adds = [];
if ($pids) {
    $in = implode(',', array_fill(0, count($pids), '?'));
    $q = db()->prepare("SELECT id, product_id, name, price_delta, is_default FROM product_variations WHERE product_id IN ($in) AND is_available = 1 ORDER BY sort_order, id");
    $q->execute($pids);
    foreach ($q->fetchAll() as $v) $vars[(int)$v['product_id']][] = ['id' => (int)$v['id'], 'name' => $v['name'], 'delta' => (float)$v['price_delta'], 'default' => (bool)$v['is_default']];
    $q = db()->prepare("SELECT id, product_id, name, price, max_qty FROM product_addons WHERE product_id IN ($in) AND is_available = 1 ORDER BY sort_order, id");
    $q->execute($pids);
    foreach ($q->fetchAll() as $a) $adds[(int)$a['product_id']][] = ['id' => (int)$a['id'], 'name' => $a['name'], 'price' => (float)$a['price'], 'max' => max(1, (int)$a['max_qty'])];
}
$byCat = [];
foreach ($prodRows as $p) {
    $byCat[(int)$p['category_id']][] = [
        'id'          => (int)$p['id'],
        'name'        => $p['name'],
        'description' => $p['description'],
        'price'       => (float)$p['price'],
        'image'       => public_url($p['image_path']),
        'featured'    => (bool)$p['is_featured'],
        'variations'  => $vars[(int)$p['id']] ?? [],
        'addons'      => $adds[(int)$p['id']] ?? [],
    ];
}

$menu = [];
foreach ($cats as $c) {
    $items = $byCat[(int)$c['id']] ?? [];
    if ($items) $menu[] = ['id' => (int)$c['id'], 'name' => $c['name'], 'items' => $items];
    unset($byCat[(int)$c['id']]);
}
$rest = array_merge(...array_values($byCat ?: [[]]));          // products with no/inactive category
if ($rest) $menu[] = ['id' => 0, 'name' => 'More', 'items' => $rest];

$st = db()->prepare('SELECT weekday, open_time, close_time, is_closed FROM restaurant_hours
                     WHERE restaurant_id = ? ORDER BY weekday, open_time');
$st->execute([$rid]);
$hourRows = $st->fetchAll();
$status = open_status($hourRows);

$zs = db()->prepare('SELECT id, name, fee, min_order, estimated_time FROM delivery_zones WHERE restaurant_id = ? AND is_active = 1 ORDER BY name');
$zs->execute([$rid]);
$zones = array_map(fn($z) => ['id' => (int)$z['id'], 'name' => $z['name'], 'fee' => (float)$z['fee'], 'min' => (float)$z['min_order'], 'eta' => $z['estimated_time'] !== null ? (int)$z['estimated_time'] : null], $zs->fetchAll());
$reviewStatsQ = db()->prepare('SELECT ROUND(AVG(rating),1) AS average,COUNT(*) AS total FROM reviews WHERE restaurant_id=? AND is_approved=1');
$reviewStatsQ->execute([$rid]); $reviewStats = $reviewStatsQ->fetch() ?: ['average' => null, 'total' => 0];
$reviewQ = db()->prepare('SELECT rv.rating,rv.title,rv.comment,rv.created_at,u.full_name FROM reviews rv JOIN users u ON u.id=rv.customer_user_id WHERE rv.restaurant_id=? AND rv.is_approved=1 ORDER BY rv.created_at DESC LIMIT 8');
$reviewQ->execute([$rid]);
$reviewList = array_map(static function (array $review): array {
    return ['rating' => (int)$review['rating'], 'title' => $review['title'], 'comment' => $review['comment'],
        'customer' => explode(' ', trim((string)$review['full_name']))[0] ?: 'Customer', 'date' => date('M j, Y', strtotime($review['created_at']))];
}, $reviewQ->fetchAll());

// Dine-in mode is decided by the signed table context in the session (set by order.php scanning the table's QR), never by a browser parameter.
start_secure_session();
$table = null;
if (!empty($_SESSION['table_ctx']) && (int)$_SESSION['table_ctx']['restaurant_id'] === $rid) {
    $tq = db()->prepare('SELECT table_number, label FROM restaurant_tables WHERE id = ? AND restaurant_id = ?');
    $tq->execute([(int)$_SESSION['table_ctx']['table_id'], $rid]);
    if ($t = $tq->fetch()) $table = ['label' => $t['label'] ?: 'Table ' . $t['table_number']];
}
$viewer = current_user();
$canFavorite = $viewer && $viewer['user_type'] === 'customer';
$isFavorite = false;
if ($canFavorite) {
    $fav = db()->prepare('SELECT 1 FROM favorites WHERE user_id=? AND restaurant_id=? AND product_id IS NULL LIMIT 1');
    $fav->execute([(int)$viewer['id'], $rid]);
    $isFavorite = (bool)$fav->fetchColumn();
}

$hex = fn($c, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c) ? $c : $d;

json_out(['ok' => true,
    'restaurant' => [
        'id' => $rid, 'slug' => $r['slug'], 'name' => $r['name'], 'cuisine' => $r['cuisine'], 'city' => $r['city'],
        'description' => $r['description'], 'phone' => $r['phone'], 'address' => $r['address'],
        'website_url' => normalize_https_website_url($r['website_url'] ?? '') ?: null,
        'cover' => public_url($r['cover_path']), 'logo' => public_url($r['logo_path']),
        'delivery' => (bool)$r['delivery_enabled'], 'pickup' => (bool)$r['pickup_enabled'], 'dinein' => (bool)$r['dinein_enabled'],
        'mpesa' => mpesa_is_configured(),
        'brand' => $hex($r['primary_color'], '#e63946'), 'ink' => $hex($r['secondary_color'], '#1d3557'),
        'open' => $status['open'], 'until' => $status['until'],
        'favorite_available' => (bool)$canFavorite, 'is_favorite' => $isFavorite,
    ],
    'menu'  => $menu,
    'reviews' => ['average' => $reviewStats['average'] !== null ? (float)$reviewStats['average'] : null, 'total' => (int)$reviewStats['total'], 'items' => $reviewList],
    'hours' => array_map(fn($h) => [
        'weekday' => (int)$h['weekday'], 'closed' => (bool)$h['is_closed'],
        'open' => substr($h['open_time'], 0, 5), 'close' => substr($h['close_time'], 0, 5),
    ], $hourRows),
    'zones' => $zones,
    'table' => $table,
    'today' => (int)date('w'),
]);
