<?php
// POST {action: create|update|delete, id?, name?}
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session(); require_csrf();
$u = require_permission('manage_products');
$rid = tenant_id($u);
$in = json_input();
$action = (string)($in['action'] ?? '');
$id = (int)($in['id'] ?? 0);
$name = clean_str($in['name'] ?? '', 100);

if ($action === 'create') {
    if ($name === '') json_error('Enter a category name.', 422);
    db()->prepare('INSERT INTO categories (restaurant_id, name, sort_order) VALUES (?,?,?)')->execute([$rid, $name, 0]);
    $id = (int)db()->lastInsertId();
} elseif ($action === 'update') {
    if ($name === '') json_error('Enter a category name.', 422);
    $st = db()->prepare('UPDATE categories SET name=? WHERE id=? AND restaurant_id=? AND deleted_at IS NULL');
    $st->execute([$name, $id, $rid]);
} elseif ($action === 'delete') {
    $st = db()->prepare('UPDATE categories SET deleted_at=NOW() WHERE id=? AND restaurant_id=? AND deleted_at IS NULL');
    $st->execute([$id, $rid]);
    if (!$st->rowCount()) json_error('Category not found.', 404);
    db()->prepare('UPDATE products SET category_id=NULL WHERE category_id=? AND restaurant_id=?')->execute([$id, $rid]);
} else json_error('Invalid request.', 422);

log_activity('category.' . $action, (int)$u['id'], $rid, 'category', $id, $name ?: null);
json_out(['ok' => true, 'id' => $id]);
