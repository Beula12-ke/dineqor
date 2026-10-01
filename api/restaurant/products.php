<?php
// POST {action: create|update|delete, id?, category_id?, name, description, price, preparation_time, is_available, is_featured}
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session(); require_csrf();
$u = require_permission('manage_products');
$rid = tenant_id($u);
$in = json_input();
$action = (string)($in['action'] ?? '');
$id = (int)($in['id'] ?? 0);

if ($action === 'delete') {
    $st = db()->prepare('UPDATE products SET deleted_at=NOW() WHERE id=? AND restaurant_id=? AND deleted_at IS NULL');
    $st->execute([$id, $rid]);
    if (!$st->rowCount()) json_error('Item not found.', 404);
    log_activity('product.delete', (int)$u['id'], $rid, 'product', $id);
    json_out(['ok' => true]);
}
if (!in_array($action, ['create', 'update'], true)) json_error('Invalid request.', 422);

$name  = clean_str($in['name'] ?? '', 150);
$desc  = clean_str($in['description'] ?? '', 1000);
$price = $in['price'] ?? '';
$prep  = ($in['preparation_time'] ?? '') === '' ? null : (int)$in['preparation_time'];
$catId = (int)($in['category_id'] ?? 0) ?: null;
$avail = empty($in['is_available']) ? 0 : 1;
$feat  = empty($in['is_featured']) ? 0 : 1;

$errors = [];
if ($name === '') $errors['name'] = 'Enter a name.';
if (!is_numeric($price) || $price < 0 || $price > 1000000) $errors['price'] = 'Enter a valid price.';
if ($prep !== null && ($prep < 0 || $prep > 600)) $errors['preparation_time'] = 'Enter minutes (0-600).';
if ($errors) json_error('Please fix the highlighted fields.', 422, ['fields' => $errors]);

if ($catId) {   // category must belong to THIS restaurant
    $c = db()->prepare('SELECT 1 FROM categories WHERE id=? AND restaurant_id=? AND deleted_at IS NULL');
    $c->execute([$catId, $rid]);
    if (!$c->fetch()) json_error('Category not found.', 422);
}

if ($action === 'create') {
    $base = make_slug($name); $slug = $base; $i = 1;
    $chk = db()->prepare('SELECT 1 FROM products WHERE restaurant_id=? AND slug=?');
    while (true) { $chk->execute([$rid, $slug]); if (!$chk->fetch()) break; $slug = $base . '-' . (++$i); }
    db()->prepare('INSERT INTO products (restaurant_id, category_id, name, slug, description, price, preparation_time, is_available, is_featured)
                   VALUES (?,?,?,?,?,?,?,?,?)')->execute([$rid, $catId, $name, $slug, $desc ?: null, $price, $prep, $avail, $feat]);
    $id = (int)db()->lastInsertId();
} else {
    $st = db()->prepare('UPDATE products SET category_id=?, name=?, description=?, price=?, preparation_time=?, is_available=?, is_featured=?
                         WHERE id=? AND restaurant_id=? AND deleted_at IS NULL');
    $st->execute([$catId, $name, $desc ?: null, $price, $prep, $avail, $feat, $id, $rid]);
    $chk = db()->prepare('SELECT 1 FROM products WHERE id=? AND restaurant_id=? AND deleted_at IS NULL');
    $chk->execute([$id, $rid]);
    if (!$chk->fetch()) json_error('Item not found.', 404);
}
log_activity('product.' . $action, (int)$u['id'], $rid, 'product', $id, $name, ['price' => (float)$price]);
json_out(['ok' => true, 'id' => $id]);
