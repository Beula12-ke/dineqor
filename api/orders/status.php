<?php
// Public order tracking, protected by a signed token: GET ?r=slug&n=260928-001&t=token
require_once __DIR__ . '/../../includes/orders.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('Method not allowed', 405);

$slug = clean_str($_GET['r'] ?? '', 80); $num = clean_str($_GET['n'] ?? '', 30); $tok = clean_str($_GET['t'] ?? '', 40);
$st = db()->prepare('SELECT id, name, phone FROM restaurants WHERE slug = ? AND deleted_at IS NULL');
$st->execute([$slug]);
$r = $st->fetch();
if (!$r || !hash_equals(order_token((int)$r['id'], $num), $tok)) json_error('Order not found.', 404);

$st = db()->prepare('SELECT id, order_number, order_type, status, subtotal, delivery_fee, total_amount, payment_status, payment_method,
                            customer_name, special_instructions, created_at, cancellation_reason
                     FROM orders WHERE restaurant_id = ? AND order_number = ?');
$st->execute([(int)$r['id'], $num]);
$o = $st->fetch();
if (!$o) json_error('Order not found.', 404);

$it = db()->prepare('SELECT product_name, variation_name, quantity, line_total, addons_json FROM order_items WHERE order_id = ? ORDER BY id');
$it->execute([$o['id']]);
$items = array_map(function ($i) {
    $a = $i['addons_json'] ? json_decode($i['addons_json'], true) : [];
    return ['name' => $i['product_name'], 'variation' => $i['variation_name'], 'qty' => (int)$i['quantity'],
            'total' => (float)$i['line_total'], 'addons' => array_map(fn($x) => $x['qty'] . '× ' . $x['name'], $a ?: [])];
}, $it->fetchAll());
unset($o['id']);
json_out(['ok' => true, 'order' => $o, 'items' => $items, 'restaurant' => ['name' => $r['name'], 'phone' => $r['phone']]]);
