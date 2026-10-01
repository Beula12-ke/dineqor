<?php
// Everything the POS screen needs in one call: categories, products (+variations+addons), and tables for dine-in.
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('manage_pos');
$rid = tenant_id($u);
$pdo = db();

$cats = $pdo->prepare('SELECT id, name FROM categories WHERE restaurant_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY sort_order, name');
$cats->execute([$rid]);
$cats = $cats->fetchAll();

$prodSt = $pdo->prepare('SELECT id, category_id, name, price, image_path FROM products
    WHERE restaurant_id=? AND is_active=1 AND is_available=1 AND deleted_at IS NULL ORDER BY sort_order, name LIMIT 500');
$prodSt->execute([$rid]);
$prods = $prodSt->fetchAll();
$pids = array_column($prods, 'id');
$vars = $adds = [];
if ($pids) {
    $in = implode(',', array_fill(0, count($pids), '?'));
    $q = $pdo->prepare("SELECT id, product_id, name, price_delta FROM product_variations WHERE product_id IN ($in) AND is_available=1 ORDER BY sort_order,id");
    $q->execute($pids);
    foreach ($q->fetchAll() as $v) $vars[(int)$v['product_id']][] = ['id' => (int)$v['id'], 'name' => $v['name'], 'delta' => (float)$v['price_delta']];
    $q = $pdo->prepare("SELECT id, product_id, name, price, max_qty FROM product_addons WHERE product_id IN ($in) AND is_available=1 ORDER BY sort_order,id");
    $q->execute($pids);
    foreach ($q->fetchAll() as $a) $adds[(int)$a['product_id']][] = ['id' => (int)$a['id'], 'name' => $a['name'], 'price' => (float)$a['price'], 'max' => max(1, (int)$a['max_qty'])];
}
$products = array_map(fn($p) => [
    'id' => (int)$p['id'], 'category_id' => $p['category_id'] ? (int)$p['category_id'] : 0, 'name' => $p['name'],
    'price' => (float)$p['price'], 'image' => public_url($p['image_path']),
    'variations' => $vars[(int)$p['id']] ?? [], 'addons' => $adds[(int)$p['id']] ?? [],
], $prods);

$tables = $pdo->prepare("SELECT id, table_number, label, status FROM restaurant_tables WHERE restaurant_id=? ORDER BY table_number");
$tables->execute([$rid]);

json_out(['ok' => true, 'csrf' => csrf_token(), 'categories' => $cats, 'products' => $products, 'tables' => $tables->fetchAll()]);
