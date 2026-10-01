<?php
// Authenticated live status for one of the signed-in customer's own orders.
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('Method not allowed.', 405);
$u = require_user_type('customer');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($id < 1) json_error('Order not found.', 404);
$st = db()->prepare("SELECT o.id,o.status,o.order_type,o.payment_method,o.payment_status,o.created_at,o.cancellation_reason,
    (SELECT MAX(COALESCE(p.preparation_time,0)) FROM order_items oi
     LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=o.id) AS prep_minutes,
    (SELECT MAX(h.created_at) FROM order_status_history h WHERE h.order_id=o.id) AS last_status_at
    FROM orders o WHERE o.id=? AND o.customer_user_id=? LIMIT 1");
$st->execute([$id, (int)$u['id']]);
$order = $st->fetch();
if (!$order) json_error('Order not found.', 404);
$prepMinutes = (int)($order['prep_minutes'] ?? 0); if ($prepMinutes < 1) $prepMinutes = 25;
$etaAt = date('Y-m-d H:i:s', strtotime($order['created_at']) + $prepMinutes * 60);
json_out([
    'ok' => true,
    'status' => $order['status'],
    'order_type' => $order['order_type'],
    'payment_method' => $order['payment_method'],
    'payment_status' => $order['payment_status'],
    'cancellation_reason' => $order['cancellation_reason'],
    'prep_minutes' => $prepMinutes,
    'eta_at' => $etaAt,
    'last_status_at' => $order['last_status_at'] ?: $order['created_at'],
]);
