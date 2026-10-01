<?php
// Small authenticated status snapshot for the customer's recent orders list.
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('Method not allowed.', 405);
$u = require_user_type('customer');
$st = db()->prepare('SELECT id,status,order_type,cancellation_reason,payment_status,payment_method FROM orders WHERE customer_user_id=? ORDER BY created_at DESC LIMIT 100');
$st->execute([(int)$u['id']]);
$orders = array_map(static function (array $order): array {
    $order['id'] = (int)$order['id'];
    return $order;
}, $st->fetchAll());
json_out(['ok' => true, 'orders' => $orders]);
