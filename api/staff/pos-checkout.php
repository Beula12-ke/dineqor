<?php
// Staff-entered sale at the counter. Prices come ONLY from price_cart() (server-side, re-reads the DB) -
// a discount amount is the one thing the staff member is trusted to enter, and it is still capped server-side.
// body: { order_type: 'dine_in'|'takeaway'|'pos', table_id?, items:[...], payment_method, discount_amount?,
//         customer_name?, customer_phone?, notes? }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orders.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session(); require_csrf();
$u = require_permission('manage_pos');
$rid = tenant_id($u);
$pdo = db();

$in = json_input();
$type = (string)($in['order_type'] ?? '');
if (!in_array($type, ['dine_in', 'takeaway', 'pos'], true)) json_error('Choose an order type.', 422);

$tableId = null;
if ($type === 'dine_in') {
    $tableId = (int)($in['table_id'] ?? 0);
    $chk = $pdo->prepare('SELECT id FROM restaurant_tables WHERE id=? AND restaurant_id=?');
    $chk->execute([$tableId, $rid]);
    if (!$chk->fetch()) json_error('Choose a valid table.', 422, ['fields' => ['table_id' => 'Choose a table.']]);
}

$method = (string)($in['payment_method'] ?? 'cash');
if (!in_array($method, ['cash', 'card', 'mpesa', 'bank', 'wallet', 'other'], true)) json_error('Choose a payment method.', 422);

$cart = price_cart($rid, (array)($in['items'] ?? []));
$subtotal = $cart['subtotal'];

$discount = $in['discount_amount'] ?? 0;
if (!is_numeric($discount) || $discount < 0) json_error('Enter a valid discount amount.', 422, ['fields' => ['discount_amount' => 'Enter a valid amount.']]);
$discount = min(round((float)$discount, 2), $subtotal);       // never below zero total, server-capped
$total = round($subtotal - $discount, 2);

$name  = clean_str($in['customer_name'] ?? '', 150) ?: null;
$phone = clean_str($in['customer_phone'] ?? '', 30) ?: null;
$notes = clean_str($in['notes'] ?? '', 500) ?: null;

try {
    $pdo->beginTransaction();

    $dec = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id=? AND restaurant_id=? AND track_stock=1 AND stock_quantity IS NOT NULL AND stock_quantity >= ?');
    $chkStock = $pdo->prepare('SELECT track_stock, stock_quantity FROM products WHERE id=? FOR UPDATE');
    foreach ($cart['stock'] as $pid => $q) {
        $chkStock->execute([$pid]); $sp = $chkStock->fetch();
        if ($sp && $sp['track_stock'] && $sp['stock_quantity'] !== null) {
            $dec->execute([$q, $pid, $rid, $q]);
            if (!$dec->rowCount()) { $pdo->rollBack(); json_error('An item just sold out. Please review the order.', 409); }
        }
    }

    $prefix = date('ymd'); $orderId = 0; $number = '';
    $ins = $pdo->prepare("INSERT INTO orders (restaurant_id, customer_user_id, table_id, order_number, order_type, source, status,
            subtotal, discount_amount, total_amount, payment_status, payment_method, customer_name, customer_phone,
            special_instructions, staff_user_id, accepted_at)
        VALUES (?,NULL,?,?,?, 'pos', 'confirmed', ?,?,?, 'paid', ?, ?,?, ?, ?, NOW())");
    for ($try = 0; $try < 6 && !$orderId; $try++) {
        $mx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(order_number,'-',-1) AS UNSIGNED)) FROM orders WHERE restaurant_id=? AND order_number LIKE ?");
        $mx->execute([$rid, $prefix . '-%']);
        $number = sprintf('%s-%03d', $prefix, (int)$mx->fetchColumn() + 1 + $try);
        try {
            $ins->execute([$rid, $tableId, $number, $type, $subtotal, $discount, $total, $method, $name, $phone, $notes, (int)$u['id']]);
            $orderId = (int)$pdo->lastInsertId();
        } catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }
    }
    if (!$orderId) throw new RuntimeException('could not allocate order number');

    $li = $pdo->prepare('INSERT INTO order_items (order_id, product_id, variation_id, product_name, variation_name, unit_price, quantity, line_total, addons_json, addons_total, notes)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($cart['rows'] as $row) {
        $li->execute([$orderId, $row['product_id'], $row['variation_id'], $row['product_name'], $row['variation_name'],
            $row['unit_price'], $row['quantity'], $row['line_total'], $row['addons_json'], $row['addons_total'], $row['notes']]);
    }
    $recipeError=consume_recipe_inventory($pdo,$rid,$cart['rows'],$orderId,(int)$u['id']);
    if ($recipeError) { $pdo->rollBack(); json_error($recipeError,409); }
    $pdo->prepare("INSERT INTO payments (restaurant_id, order_id, amount, method, status, paid_at) VALUES (?,?,?,?, 'completed', NOW())")
        ->execute([$rid, $orderId, $total, $method]);
    $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'confirmed', ?, 'POS sale')")
        ->execute([$orderId, (int)$u['id']]);
    if ($tableId) $pdo->prepare("UPDATE restaurant_tables SET status='occupied' WHERE id=? AND restaurant_id=?")->execute([$tableId, $rid]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('pos checkout: ' . $e->getMessage());
    json_error('Could not complete the sale. Please try again.', 500);
}

log_activity('order.pos_sale', (int)$u['id'], $rid, 'order', $orderId, "Order $number");
json_out(['ok' => true, 'order_id' => $orderId, 'order_number' => $number, 'subtotal' => $subtotal,
          'discount' => $discount, 'total' => $total, 'items' => $cart['rows']], 201);
