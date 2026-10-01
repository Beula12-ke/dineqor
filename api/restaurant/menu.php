<?php
// Owner/staff: list own categories + products. Tenant comes from the session only.
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('view_products');
$rid = tenant_id($u);
$c = db()->prepare('SELECT id, name, sort_order, is_active FROM categories WHERE restaurant_id=? AND deleted_at IS NULL ORDER BY sort_order, name');
$c->execute([$rid]);
$p = db()->prepare('SELECT id, category_id, name, description, price, image_path, preparation_time, is_available, is_featured
                    FROM products WHERE restaurant_id=? AND deleted_at IS NULL ORDER BY sort_order, name');
$p->execute([$rid]);
$products = array_map(function ($r) {
    $r['id'] = (int)$r['id']; $r['category_id'] = $r['category_id'] ? (int)$r['category_id'] : null;
    $r['price'] = (float)$r['price']; $r['image'] = public_url($r['image_path']); unset($r['image_path']);
    return $r;
}, $p->fetchAll());
json_out(['ok' => true, 'csrf' => csrf_token(), 'can_manage' => has_permission($u, 'manage_products'),
          'categories' => $c->fetchAll(), 'products' => $products]);
