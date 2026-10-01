<?php
// A customer may save or remove active restaurants from their own account only.
require_once __DIR__ . '/../../includes/auth.php';
$u = require_user_type('customer');
$userId = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $restaurantId = (int)($_GET['restaurant_id'] ?? 0);
    if ($restaurantId > 0) {
        $restaurant = db()->prepare("SELECT id FROM restaurants WHERE id=? AND status='active' AND deleted_at IS NULL");
        $restaurant->execute([$restaurantId]);
        if (!$restaurant->fetchColumn()) json_error('Restaurant not found.', 404);
        $q = db()->prepare('SELECT 1 FROM favorites WHERE user_id=? AND restaurant_id=? AND product_id IS NULL LIMIT 1');
        $q->execute([$userId, $restaurantId]);
        json_out(['ok' => true, 'favorite' => (bool)$q->fetchColumn()]);
    }
    $q = db()->prepare("SELECT r.id,r.slug,r.name,r.cuisine,r.city,r.cover_path,r.primary_color,f.created_at
        FROM favorites f JOIN restaurants r ON r.id=f.restaurant_id
        WHERE f.user_id=? AND f.product_id IS NULL AND r.status='active' AND r.deleted_at IS NULL
        ORDER BY f.created_at DESC LIMIT 100");
    $q->execute([$userId]);
    $items = array_map(static function (array $r): array {
        return ['id' => (int)$r['id'], 'slug' => $r['slug'], 'name' => $r['name'], 'cuisine' => $r['cuisine'],
            'city' => $r['city'], 'cover' => public_url($r['cover_path']), 'color' => $r['primary_color'], 'saved_at' => $r['created_at']];
    }, $q->fetchAll());
    json_out(['ok' => true, 'favorites' => $items, 'csrf' => csrf_token()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
start_secure_session(); require_csrf();
$in = json_input();
$restaurantId = (int)($in['restaurant_id'] ?? 0);
$action = (string)($in['action'] ?? '');
if ($restaurantId < 1 || !in_array($action, ['add','remove'], true)) json_error('Choose a restaurant to save or remove.', 422);
$restaurant = db()->prepare("SELECT id FROM restaurants WHERE id=? AND status='active' AND deleted_at IS NULL");
$restaurant->execute([$restaurantId]);
if (!$restaurant->fetchColumn()) json_error('Restaurant not found.', 404);

if ($action === 'add') {
    db()->prepare('INSERT IGNORE INTO favorites (user_id,restaurant_id,product_id) VALUES (?,?,NULL)')->execute([$userId, $restaurantId]);
} else {
    db()->prepare('DELETE FROM favorites WHERE user_id=? AND restaurant_id=? AND product_id IS NULL')->execute([$userId, $restaurantId]);
}
log_activity('favorite.restaurant_' . $action, $userId, $restaurantId, 'restaurant', $restaurantId);
json_out(['ok' => true, 'favorite' => $action === 'add']);
