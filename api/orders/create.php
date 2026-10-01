<?php
// Place an order (guests and logged-in customers). POST JSON:
// {slug, order_type, name, phone, email?, address?, zone_id?, instructions?, items:[{product_id, variation_id?, qty, addons?, notes?}]}
// Dine-in is detected server-side from the table's QR session context - never from a value the browser sends.
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mpesa.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session();
require_csrf();

$in = json_input();
$paymentMethod = (string)($in['payment_method'] ?? 'cash');
if (!in_array($paymentMethod, ['cash','mpesa'], true)) json_error('Choose cash or M-Pesa.', 422, ['fields'=>['payment_method'=>'Choose a payment method.']]);
if ($paymentMethod === 'mpesa' && !mpesa_is_configured()) json_error('M-Pesa is not configured yet. Please choose cash or contact the restaurant.', 409);
$mpesaPhone = $paymentMethod === 'mpesa' ? mpesa_normalize_phone((string)($in['phone'] ?? '')) : null;
if ($paymentMethod === 'mpesa' && !$mpesaPhone) json_error('Enter a valid Kenyan M-Pesa phone number.', 422, ['fields'=>['phone'=>'Use a number like 0712 345 678 or 254712345678.']]);
$r = resolve_public_restaurant(clean_str($in['slug'] ?? '', 80) ?: null);
if (!$r) json_error('Restaurant not found.', 404);
$rid = (int)$r['id'];
$pdo = db();

// --- who / where / how
$name  = clean_str($in['name'] ?? '', 150);
$phone = clean_str($in['phone'] ?? '', 30);
$email = strtolower(clean_str($in['email'] ?? '', 150));
$errors = [];
if (mb_strlen($name) < 2) $errors['name'] = 'Enter your name.';
if (!preg_match('/^\+?[0-9 ()-]{9,20}$/', $phone)) $errors['phone'] = 'Enter a valid phone number.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email or leave it blank.';

$table = session_table($rid);
$type = null; $source = 'website'; $tableId = null;
if ($table) {
    if (!$r['dinein_enabled']) json_error('This restaurant is not taking dine-in orders right now.', 409);
    $type = 'qr_table'; $source = 'qr'; $tableId = (int)$table['id'];
} else {
    $type = (string)($in['order_type'] ?? '');
    if ($type === 'pickup') { if (!$r['pickup_enabled']) json_error('Pickup is not available at this restaurant.', 409); }
    elseif ($type === 'delivery') { if (!$r['delivery_enabled']) json_error('Delivery is not available at this restaurant.', 409); }
    else $errors['order_type'] = 'Choose pickup or delivery.';
}

$address = clean_str($in['address'] ?? '', 500);
$zoneId = (int)($in['zone_id'] ?? 0);
if ($type === 'delivery' && mb_strlen($address) < 5) $errors['address'] = 'Enter your delivery address.';
if ($errors) json_error('Please fix the highlighted fields.', 422, ['fields' => $errors]);

// --- open now? (dine-in orders proceed even if the kitchen hasn't set hours - staff already seated the guest)
if (!$table) {
    $hoursSt = $pdo->prepare('SELECT weekday, open_time, close_time, is_closed FROM restaurant_hours WHERE restaurant_id = ?');
    $hoursSt->execute([$rid]);
    $os = open_status($hoursSt->fetchAll());
    if ($os['open'] === false) json_error($r['name'] . ' is closed right now. Please check the opening hours.', 409);
}

// --- basic abuse limit: orders from one IP in 10 minutes
$lim = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE action = 'order.created' AND ip_address <=> ? AND created_at > (NOW() - INTERVAL 10 MINUTE)");
$lim->execute([client_ip_bin()]);
if ((int)$lim->fetchColumn() >= 8) json_error('Too many orders in a short time. Please wait a few minutes.', 429);

// --- price everything on the server
$cart = price_cart($rid, (array)($in['items'] ?? []));
$subtotal = $cart['subtotal'];

$fee = 0.0; $zone = null;
if ($type === 'delivery') {
    $zs = $pdo->prepare('SELECT id, name, fee, min_order FROM delivery_zones WHERE restaurant_id = ? AND is_active = 1 ORDER BY name');
    $zs->execute([$rid]);
    $zones = $zs->fetchAll();
    if ($zones) {
        foreach ($zones as $z) if ((int)$z['id'] === $zoneId) $zone = $z;
        if (!$zone) json_error('Choose your delivery area.', 422, ['fields' => ['zone_id' => 'Choose your delivery area.']]);
        if ($subtotal < (float)$zone['min_order']) json_error('The minimum order for ' . $zone['name'] . ' is KSh ' . number_format((float)$zone['min_order'], 0) . '.', 422);
        $fee = (float)$zone['fee'];
    }
}
$total = round($subtotal + $fee, 2);

$u = current_user();
$customerId = ($u && $u['user_type'] === 'customer') ? (int)$u['id'] : null;
$requestedLoyaltyPoints=$in['loyalty_points']??0;
if(!is_numeric($requestedLoyaltyPoints)||(float)$requestedLoyaltyPoints<0||(float)$requestedLoyaltyPoints>1000000000||(int)$requestedLoyaltyPoints!=(float)$requestedLoyaltyPoints)json_error('Invalid loyalty points selection.',422);
$requestedLoyaltyPoints=(int)$requestedLoyaltyPoints;$loyaltyDiscount=0.0;
$notes = $type === 'delivery' ? ('Deliver to: ' . $address . ($zone ? ' (' . $zone['name'] . ')' : '')) : null;

try {
    $pdo->beginTransaction();

    if ($requestedLoyaltyPoints>0) {
        if (!$customerId) { $pdo->rollBack(); json_error('Sign in to redeem loyalty points.',422); }
        $rewardQ=$pdo->prepare('SELECT is_enabled,redemption_points,redemption_value FROM loyalty_settings WHERE restaurant_id=? FOR UPDATE');
        $rewardQ->execute([$rid]);$reward=$rewardQ->fetch();
        if (!$reward || !(int)$reward['is_enabled'] || !(int)$reward['redemption_points'] || (float)$reward['redemption_value']<=0) { $pdo->rollBack(); json_error('Loyalty redemption is not available at this restaurant.',409); }
        $accountQ=$pdo->prepare('SELECT id,points_balance FROM loyalty_accounts WHERE restaurant_id=? AND user_id=? FOR UPDATE');
        $accountQ->execute([$rid,$customerId]);$account=$accountQ->fetch();
        if(!$account){$pdo->rollBack();json_error('You do not have loyalty points to redeem yet.',409);}
        $accountId=(int)$account['id'];$balance=(int)$account['points_balance'];
        $step=(int)$reward['redemption_points'];$value=(float)$reward['redemption_value'];$maxSteps=min((int)floor($balance/$step),(int)floor($subtotal/$value));
        if ($requestedLoyaltyPoints%$step!==0 || $requestedLoyaltyPoints>$balance || $requestedLoyaltyPoints>$maxSteps*$step) { $pdo->rollBack(); json_error('Your loyalty balance changed or does not cover this reward. Refresh and try again.',409); }
        $loyaltyDiscount=round(($requestedLoyaltyPoints/$step)*$value,2);$total=round(max(0,$subtotal-$loyaltyDiscount)+$fee,2);
    }
    if ($paymentMethod === 'mpesa' && (abs($total-round($total)) > 0.001 || $total < 1)) {
        $pdo->rollBack(); json_error('M-Pesa checkout supports whole-shilling totals of at least KSh 1. Adjust the cart or choose cash.', 422);
    }

    // stock: decrement only if enough is left (guards against two people ordering the last one)
    $dec = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND restaurant_id = ? AND track_stock = 1 AND stock_quantity IS NOT NULL AND stock_quantity >= ?');
    $chk = $pdo->prepare('SELECT track_stock, stock_quantity FROM products WHERE id = ? FOR UPDATE');
    foreach ($cart['stock'] as $pid => $q) {
        $chk->execute([$pid]); $sp = $chk->fetch();
        if ($sp && $sp['track_stock'] && $sp['stock_quantity'] !== null) {
            $dec->execute([$q, $pid, $rid, $q]);
            if (!$dec->rowCount()) { $pdo->rollBack(); json_error('An item just sold out. Please review your order.', 409); }
        }
    }

    // unique, readable order number per restaurant per day: 260928-001
    $prefix = date('ymd'); $orderId = 0; $number = '';
    $ins = $pdo->prepare("INSERT INTO orders (restaurant_id, customer_user_id, table_id, order_number, order_type, source, status,
            subtotal, delivery_fee, discount_amount, total_amount, payment_status, payment_method, customer_name, customer_phone, customer_email,
            delivery_notes, special_instructions)
        VALUES (?,?,?,?,?,?, 'new', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    for ($try = 0; $try < 6 && !$orderId; $try++) {
        $mx = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(order_number, '-', -1) AS UNSIGNED)) FROM orders WHERE restaurant_id = ? AND order_number LIKE ?");
        $mx->execute([$rid, $prefix . '-%']);
        $number = sprintf('%s-%03d', $prefix, (int)$mx->fetchColumn() + 1 + $try);
        try {
            $ins->execute([$rid, $customerId, $tableId, $number, $type, $source, $subtotal, $fee, $loyaltyDiscount, $total,
                $paymentMethod === 'mpesa' ? 'pending' : 'unpaid', $paymentMethod, $name, $phone, $email ?: null, $notes,
                clean_str($in['instructions'] ?? '', 1000) ?: null]);
            $orderId = (int)$pdo->lastInsertId();
        } catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }   // number taken: try the next one
    }
    if (!$orderId) throw new RuntimeException('could not allocate order number');

    if ($requestedLoyaltyPoints>0) {
        $pdo->prepare("INSERT INTO loyalty_transactions (account_id,restaurant_id,customer_user_id,order_id,type,points,description) VALUES (?,?,?,?,'redeem',?,'Points redeemed at checkout')")
            ->execute([$accountId,$rid,$customerId,$orderId,-$requestedLoyaltyPoints]);
        $debit=$pdo->prepare('UPDATE loyalty_accounts SET points_balance=points_balance-? WHERE id=? AND points_balance>=?');$debit->execute([$requestedLoyaltyPoints,$accountId,$requestedLoyaltyPoints]);
        if(!$debit->rowCount()) { $pdo->rollBack(); json_error('Your loyalty balance changed. Refresh and try again.',409); }
    }

    $li = $pdo->prepare('INSERT INTO order_items (order_id, product_id, variation_id, product_name, variation_name, unit_price, quantity, line_total, addons_json, addons_total, notes)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($cart['rows'] as $row) {
        $li->execute([$orderId, $row['product_id'], $row['variation_id'], $row['product_name'], $row['variation_name'],
            $row['unit_price'], $row['quantity'], $row['line_total'], $row['addons_json'], $row['addons_total'], $row['notes']]);
    }
    $recipeError=consume_recipe_inventory($pdo,$rid,$cart['rows'],$orderId,$customerId);
    if ($recipeError) { $pdo->rollBack(); json_error($recipeError,409); }
    $paymentQ=$pdo->prepare("INSERT INTO payments (restaurant_id, order_id, amount, method, status, notes, mpesa_phone) VALUES (?,?,?,?,'pending',?,?)");
    $paymentQ->execute([$rid,$orderId,$total,$paymentMethod,$paymentMethod==='mpesa'?'Awaiting M-Pesa payment':'Cash payment due', $mpesaPhone]);
    $paymentId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'new', ?, 'Order placed')")->execute([$orderId, $customerId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('order create: ' . $e->getMessage());
    json_error('We could not place your order. Please try again.', 500);
}

log_activity('order.created', $customerId, $rid, 'order', $orderId, "Order $number");
$paymentMessage=null;
if ($paymentMethod === 'mpesa') {
    try {
        $stk=mpesa_start_stk((string)$mpesaPhone,(int)$total,'DQ'.$orderId,'Dineqor order '.$number);
        if ((string)($stk['ResponseCode'] ?? '') !== '0' || empty($stk['CheckoutRequestID']) || empty($stk['MerchantRequestID'])) {
            throw new RuntimeException(clean_str($stk['ResponseDescription'] ?? 'Daraja did not accept the payment prompt.',180));
        }
        $pdo->prepare('UPDATE payments SET checkout_request_id=?,merchant_request_id=?,notes=\'M-Pesa prompt sent\' WHERE id=?')
            ->execute([(string)$stk['CheckoutRequestID'],(string)$stk['MerchantRequestID'],$paymentId]);
        $paymentMessage='M-Pesa prompt sent. Confirm it on your phone; your order will show as paid after Safaricom confirms the payment.';
    } catch (Throwable $e) {
        error_log('Dineqor M-Pesa initiation failed for order '.$orderId.': '.$e->getMessage());
        if (!($e instanceof MpesaTransportException)) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE payments SET status=\'failed\',notes=\'Could not start the M-Pesa prompt\' WHERE id=?')->execute([$paymentId]);
                $pdo->prepare('UPDATE orders SET payment_status=\'failed\' WHERE id=? AND payment_status=\'pending\'')->execute([$orderId]);
                refund_mpesa_order_loyalty($pdo,$orderId);
                $pdo->commit();
            } catch (Throwable $refundError) {
                if($pdo->inTransaction())$pdo->rollBack();
                error_log('M-Pesa failure cleanup failed for order '.$orderId.': '.$refundError->getMessage());
            }
            $paymentMessage='The M-Pesa service rejected the prompt. Your order remains unpaid; contact the restaurant before paying another way.';
        } else {
            // A timeout can happen after Daraja accepted the request. Keep the order pending and points reserved
            // so an accepted payment is never treated as a definite failure without provider confirmation.
            $paymentMessage='We could not verify whether Safaricom received the prompt. Your order remains pending; contact the restaurant before trying another payment.';
        }
    }
}
$token = order_token($rid, $number);
json_out(['ok' => true, 'order_number' => $number, 'total' => $total, 'discount' => $loyaltyDiscount, 'loyalty_points_used' => $requestedLoyaltyPoints, 'token' => $token,
          'payment_status'=>$paymentMethod==='mpesa'?'pending':'unpaid','payment_message'=>$paymentMessage,
          'track_url' => order_track_url($r['slug'], $number, $token)], 201);
