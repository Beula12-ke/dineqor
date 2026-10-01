<?php
// Recent customer activity for the logged-in restaurant's staff.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orders.php';
$u = require_permission('view_orders');
$rid = tenant_id($u);
$pdo = db();

$st = $pdo->prepare("SELECT o.id, o.order_number, o.order_type, o.source, o.status, o.subtotal, o.delivery_fee, o.discount_amount, o.total_amount, o.payment_status, o.payment_method,
        o.customer_name, o.customer_phone, o.customer_email, o.delivery_notes, o.special_instructions, o.created_at, t.table_number, t.label AS table_label
    FROM orders o LEFT JOIN restaurant_tables t ON t.id = o.table_id
    WHERE o.restaurant_id = ?
    ORDER BY o.created_at DESC LIMIT 150");
$st->execute([$rid]);
$orders = $st->fetchAll();

$items = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT order_id, product_name, variation_name, quantity, line_total, addons_json, notes FROM order_items WHERE order_id IN ($in) ORDER BY id");
    $q->execute($ids);
    foreach ($q->fetchAll() as $i) {
        $a = $i['addons_json'] ? json_decode($i['addons_json'], true) : [];
        $items[(int)$i['order_id']][] = ['name' => $i['product_name'], 'variation' => $i['variation_name'], 'qty' => (int)$i['quantity'], 'line_total' => (float)$i['line_total'],
            'addons' => array_map(fn($x) => $x['qty'] . '× ' . $x['name'], $a ?: []), 'notes' => $i['notes']];
    }
}
foreach ($orders as &$o) {
    $o['id'] = (int)$o['id']; $o['subtotal'] = (float)$o['subtotal']; $o['delivery_fee'] = (float)$o['delivery_fee'];
    $o['discount_amount'] = (float)$o['discount_amount']; $o['total_amount'] = (float)$o['total_amount'];
    $o['items'] = $items[$o['id']] ?? [];
    $o['next'] = next_status($o['status'], $o['order_type']);
}
unset($o);
json_out(['ok' => true, 'orders' => $orders, 'can_manage' => has_permission($u, 'manage_orders')]);
