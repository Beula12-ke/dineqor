<?php
// Ordering helpers. Prices are ALWAYS recalculated here from the database; the browser only sends ids and quantities.
require_once __DIR__ . '/tenant.php';

// Secret used to sign guest order-tracking links. Generated once, stored in config/ (blocked from the web by .htaccess).
function app_secret(): string {
    static $k = null;
    if ($k !== null) return $k;
    $file = __DIR__ . '/../config/.app_key';
    if (is_file($file)) return $k = trim((string)file_get_contents($file));
    $k = bin2hex(random_bytes(32));
    if (@file_put_contents($file, $k, LOCK_EX) === false) {
        error_log('Dineqor: cannot write config/.app_key - make config/ writable');
        json_error('Ordering is not available right now. Please try again later.', 500);
    }
    return $k;
}

function order_token(int $restaurantId, string $orderNumber): string {
    return substr(hash_hmac('sha256', $restaurantId . '|' . $orderNumber, app_secret()), 0, 24);
}

function order_track_url(string $slug, string $orderNumber, string $token): string {
    return BASE_URL . '/order-status.php?r=' . rawurlencode($slug) . '&n=' . rawurlencode($orderNumber) . '&t=' . $token;
}

// Validates cart lines against the restaurant's live menu and returns priced rows + subtotal.
// $lines = [{product_id, variation_id?, qty, addons:[{id,qty}], notes?}]
function price_cart(int $rid, array $lines): array {
    if (!$lines || count($lines) > 40) json_error('Your order is empty.', 422);
    $pdo = db();
    $ids = array_values(array_unique(array_map(fn($l) => (int)($l['product_id'] ?? 0), $lines)));
    $in = implode(',', array_fill(0, count($ids), '?'));

    $st = $pdo->prepare("SELECT id, name, price, track_stock, stock_quantity FROM products
        WHERE restaurant_id = ? AND id IN ($in) AND is_active = 1 AND is_available = 1 AND deleted_at IS NULL");
    $st->execute(array_merge([$rid], $ids));
    $products = [];
    foreach ($st->fetchAll() as $p) $products[(int)$p['id']] = $p;

    $vars = $adds = [];
    $st = $pdo->prepare("SELECT id, product_id, name, price_delta, is_default FROM product_variations
        WHERE product_id IN ($in) AND is_available = 1 ORDER BY is_default DESC, sort_order, id");
    $st->execute($ids);
    foreach ($st->fetchAll() as $v) $vars[(int)$v['product_id']][(int)$v['id']] = $v;
    $st = $pdo->prepare("SELECT id, product_id, name, price, max_qty FROM product_addons
        WHERE product_id IN ($in) AND is_available = 1");
    $st->execute($ids);
    foreach ($st->fetchAll() as $a) $adds[(int)$a['product_id']][(int)$a['id']] = $a;

    $rows = []; $subtotal = 0.0; $wanted = [];
    foreach ($lines as $l) {
        $pid = (int)($l['product_id'] ?? 0);
        $qty = (int)($l['qty'] ?? 0);
        if (!isset($products[$pid])) json_error('An item in your order is no longer available. Please review your order.', 409);
        if ($qty < 1 || $qty > 50) json_error('Invalid quantity.', 422);
        $p = $products[$pid];

        $vid = (int)($l['variation_id'] ?? 0); $vname = null; $delta = 0.0;
        if (!empty($vars[$pid])) {
            if (!$vid) json_error('Please choose an option for ' . $p['name'] . '.', 422);
            if (!isset($vars[$pid][$vid])) json_error('An option you chose is no longer available.', 409);
            $vname = $vars[$pid][$vid]['name']; $delta = (float)$vars[$pid][$vid]['price_delta'];
        } else { $vid = 0; }

        $addonList = []; $addonsTotal = 0.0;
        foreach ((array)($l['addons'] ?? []) as $a) {
            $aid = (int)($a['id'] ?? 0); $aq = (int)($a['qty'] ?? 0);
            if ($aq < 1) continue;
            if (!isset($adds[$pid][$aid])) json_error('An extra you chose is no longer available.', 409);
            $ad = $adds[$pid][$aid];
            if ($aq > (int)$ad['max_qty']) json_error('You can add at most ' . (int)$ad['max_qty'] . ' × ' . $ad['name'] . '.', 422);
            $addonList[] = ['id' => $aid, 'name' => $ad['name'], 'price' => (float)$ad['price'], 'qty' => $aq];
            $addonsTotal += (float)$ad['price'] * $aq;
        }

        $unit = round((float)$p['price'] + $delta, 2);
        $line = round(($unit + $addonsTotal) * $qty, 2);
        $subtotal += $line;
        $wanted[$pid] = ($wanted[$pid] ?? 0) + $qty;
        $rows[] = [
            'product_id' => $pid, 'variation_id' => $vid ?: null, 'product_name' => $p['name'], 'variation_name' => $vname,
            'unit_price' => $unit, 'quantity' => $qty, 'line_total' => $line,
            'addons_json' => $addonList ? json_encode($addonList, JSON_UNESCAPED_UNICODE) : null,
            'addons_total' => round($addonsTotal, 2), 'notes' => clean_str($l['notes'] ?? '', 255) ?: null,
        ];
    }
    foreach ($wanted as $pid => $q) {
        $p = $products[$pid];
        if ($p['track_stock'] && $p['stock_quantity'] !== null && (int)$p['stock_quantity'] < $q)
            json_error('Sorry, only ' . max(0, (int)$p['stock_quantity']) . ' left of ' . $p['name'] . '.', 409);
    }
    return ['rows' => $rows, 'subtotal' => round($subtotal, 2), 'stock' => $wanted];
}

// Consume recipe ingredients inside the caller's order transaction. Products without recipes are unaffected.
// Returns an actionable error string when any ingredient would go below zero; otherwise returns null.
function consume_recipe_inventory(PDO $pdo, int $restaurantId, array $rows, int $orderId, ?int $userId): ?string {
    $productQty=[];
    foreach ($rows as $row) {
        $id=(int)($row['product_id'] ?? 0); $qty=(int)($row['quantity'] ?? 0);
        if ($id>0 && $qty>0) $productQty[$id]=($productQty[$id] ?? 0)+$qty;
    }
    if (!$productQty) return null;
    $ids=array_keys($productQty); $in=implode(',',array_fill(0,count($ids),'?'));
    $q=$pdo->prepare("SELECT r.product_id,r.ingredient_id,r.quantity,i.name,i.unit
        FROM recipes r JOIN products p ON p.id=r.product_id AND p.restaurant_id=?
        JOIN ingredients i ON i.id=r.ingredient_id AND i.restaurant_id=?
        WHERE r.product_id IN ($in) ORDER BY r.ingredient_id");
    $q->execute(array_merge([$restaurantId,$restaurantId],$ids));
    $required=[];
    foreach ($q->fetchAll() as $recipe) {
        $ingredientId=(int)$recipe['ingredient_id'];
        if (!isset($required[$ingredientId])) $required[$ingredientId]=['quantity'=>0.0,'name'=>$recipe['name'],'unit'=>$recipe['unit']];
        $required[$ingredientId]['quantity']+=(float)$recipe['quantity']*$productQty[(int)$recipe['product_id']];
    }
    if (!$required) return null;
    ksort($required);
    $ingredientIds=array_keys($required); $in=implode(',',array_fill(0,count($ingredientIds),'?'));
    $lock=$pdo->prepare("SELECT id,name,unit,current_stock FROM ingredients WHERE restaurant_id=? AND id IN ($in) ORDER BY id FOR UPDATE");
    $lock->execute(array_merge([$restaurantId],$ingredientIds));
    $available=[]; foreach ($lock->fetchAll() as $ingredient) $available[(int)$ingredient['id']]=$ingredient;
    foreach ($required as $id=>$need) {
        $stock=$available[$id] ?? null;
        if (!$stock) return 'An ingredient in one of these recipes no longer exists.';
        if ((float)$stock['current_stock']+0.0001 < $need['quantity']) {
            return 'Not enough '.$stock['name'].' for this order. Available: '.number_format((float)$stock['current_stock'],3).' '.$stock['unit'].'.';
        }
    }
    $update=$pdo->prepare('UPDATE ingredients SET current_stock=current_stock-? WHERE id=? AND restaurant_id=? AND current_stock>=?');
    $movement=$pdo->prepare("INSERT INTO stock_movements (restaurant_id,ingredient_id,quantity,type,reference_id,notes,user_id) VALUES (?,?,?,'sale',?,?,?)");
    foreach ($required as $id=>$need) {
        $qty=round($need['quantity'],3);
        $update->execute([$qty,$id,$restaurantId,$qty]);
        if (!$update->rowCount()) return 'Stock changed while the order was being placed. Please try again.';
        $movement->execute([$restaurantId,$id,-$qty,$orderId,'Recipe ingredients for order #'.$orderId,$userId]);
    }
    return null;
}

// Restore recipe stock if a not-yet-completed order is cancelled. Existing orders without recipe movements are ignored.
function restore_recipe_inventory(PDO $pdo, int $restaurantId, int $orderId, ?int $userId): void {
    $q=$pdo->prepare("SELECT ingredient_id,SUM(-quantity) AS quantity FROM stock_movements
        WHERE restaurant_id=? AND reference_id=? AND type='sale' AND notes=? GROUP BY ingredient_id ORDER BY ingredient_id");
    $q->execute([$restaurantId,$orderId,'Recipe ingredients for order #'.$orderId]);
    $rows=$q->fetchAll(); if (!$rows) return;
    $lock=$pdo->prepare('SELECT id FROM ingredients WHERE id=? AND restaurant_id=? FOR UPDATE');
    $add=$pdo->prepare('UPDATE ingredients SET current_stock=current_stock+? WHERE id=? AND restaurant_id=?');
    $movement=$pdo->prepare("INSERT INTO stock_movements (restaurant_id,ingredient_id,quantity,type,reference_id,notes,user_id) VALUES (?,?,?,'return',?,?,?)");
    foreach ($rows as $row) {
        $id=(int)$row['ingredient_id']; $qty=(float)$row['quantity'];
        $lock->execute([$id,$restaurantId]);
        if (!$lock->fetchColumn()) continue;
        $add->execute([$qty,$id,$restaurantId]);
        $movement->execute([$restaurantId,$id,$qty,$orderId,'Recipe stock returned for cancelled order #'.$orderId,$userId]);
    }
}

// Award points only after an authenticated customer's order is completed and paid. The unique ledger key makes retries safe.
function award_order_loyalty(PDO $pdo, int $restaurantId, int $orderId): int {
    $orderQ=$pdo->prepare("SELECT customer_user_id,subtotal,discount_amount FROM orders WHERE id=? AND restaurant_id=? AND status IN ('completed','delivered') AND payment_status='paid'");
    $orderQ->execute([$orderId,$restaurantId]); $order=$orderQ->fetch();
    if (!$order || !(int)$order['customer_user_id']) return 0;
    $settingsQ=$pdo->prepare('SELECT is_enabled,shillings_per_point,points_per_earn FROM loyalty_settings WHERE restaurant_id=?');
    $settingsQ->execute([$restaurantId]); $settings=$settingsQ->fetch();
    if (!$settings || !(int)$settings['is_enabled'] || !(int)$settings['shillings_per_point'] || !(int)$settings['points_per_earn']) return 0;
    $eligibleSpend=max(0,(float)$order['subtotal']-(float)$order['discount_amount']);
    $points=(int)floor($eligibleSpend/(int)$settings['shillings_per_point'])*(int)$settings['points_per_earn'];
    if ($points<1) return 0;
    $pdo->prepare('INSERT INTO loyalty_accounts (user_id,restaurant_id,points_balance) VALUES (?,?,0) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([(int)$order['customer_user_id'],$restaurantId]);
    $accountId=(int)$pdo->lastInsertId();
    $lock=$pdo->prepare('SELECT id FROM loyalty_accounts WHERE id=? AND user_id=? AND restaurant_id=? FOR UPDATE');$lock->execute([$accountId,(int)$order['customer_user_id'],$restaurantId]);
    if (!$lock->fetchColumn()) return 0;
    $exists=$pdo->prepare("SELECT id FROM loyalty_transactions WHERE account_id=? AND order_id=? AND type='earn' LIMIT 1");$exists->execute([$accountId,$orderId]);
    if ($exists->fetchColumn()) return 0;
    $pdo->prepare("INSERT INTO loyalty_transactions (account_id,restaurant_id,customer_user_id,order_id,type,points,description) VALUES (?,?,?,?,'earn',?,'Points earned on completed order')")
        ->execute([$accountId,$restaurantId,(int)$order['customer_user_id'],$orderId,$points]);
    $pdo->prepare('UPDATE loyalty_accounts SET points_balance=points_balance+? WHERE id=?')->execute([$points,$accountId]);
    return $points;
}

// Dine-in table for THIS restaurant, from the signed session context set when the table's QR was scanned. Never trusts the browser.
function session_table(int $rid): ?array {
    start_secure_session();
    if (empty($_SESSION['table_ctx']) || (int)$_SESSION['table_ctx']['restaurant_id'] !== $rid) return null;
    $st = db()->prepare('SELECT id, table_number, label FROM restaurant_tables WHERE id = ? AND restaurant_id = ?');
    $st->execute([(int)$_SESSION['table_ctx']['table_id'], $rid]);
    return $st->fetch() ?: null;
}

// Next status for an order, or null when finished.
function next_status(string $status, string $type): ?string {
    return match ($status) {
        'new' => 'confirmed', 'confirmed' => 'preparing', 'preparing' => 'ready',
        'ready' => $type === 'delivery' ? 'out_for_delivery' : 'completed',
        'out_for_delivery' => 'delivered',
        default => null,
    };
}

// Persist a customer-facing notification in the same transaction as the order update.
function notify_customer_order_status(PDO $pdo, int $orderId, string $status, ?string $reason = null): void {
    $messages = [
        'confirmed' => 'The restaurant confirmed your order.',
        'preparing' => 'The restaurant started preparing your order.',
        'ready' => 'Your order is ready.',
        'out_for_delivery' => 'Your order is on the way.',
        'delivered' => 'Your order was delivered.',
        'completed' => 'Your order is complete.',
        'cancelled' => 'The restaurant cancelled your order.',
    ];
    if (!isset($messages[$status])) return;
    $q = $pdo->prepare('SELECT customer_user_id, order_number FROM orders WHERE id=? LIMIT 1');
    $q->execute([$orderId]); $order = $q->fetch();
    if (!$order || !(int)$order['customer_user_id']) return;
    $message = 'Order #' . $order['order_number'] . ': ' . $messages[$status];
    if ($status === 'cancelled' && $reason) $message .= ' ' . $reason;
    $pdo->prepare('INSERT INTO customer_notifications (user_id,order_id,status,title,message) VALUES (?,?,?,?,?)')
        ->execute([(int)$order['customer_user_id'], $orderId, $status, 'Order update', $message]);
}

// Restore reserved reward points if an M-Pesa checkout fails. Caller owns the transaction.
function refund_mpesa_order_loyalty(PDO $pdo, int $orderId): void {
    $already=$pdo->prepare("SELECT id FROM loyalty_transactions WHERE order_id=? AND type='adjust'
        AND description='M-Pesa failed; redeemed points returned' LIMIT 1 FOR UPDATE");
    $already->execute([$orderId]); if($already->fetchColumn()) return;
    $q=$pdo->prepare("SELECT account_id,restaurant_id,customer_user_id,points FROM loyalty_transactions
        WHERE order_id=? AND type='redeem' AND points<0 LIMIT 1 FOR UPDATE");
    $q->execute([$orderId]); $redeem=$q->fetch();
    if (!$redeem) return;
    $points=abs((int)$redeem['points']);
    if (!$points) return;
    $pdo->prepare('UPDATE loyalty_accounts SET points_balance=points_balance+? WHERE id=?')
        ->execute([$points,(int)$redeem['account_id']]);
    $pdo->prepare("INSERT INTO loyalty_transactions (account_id,restaurant_id,customer_user_id,order_id,type,points,description)
        VALUES (?,?,?,?,'adjust',?,'M-Pesa failed; redeemed points returned')")
        ->execute([(int)$redeem['account_id'],(int)$redeem['restaurant_id'],(int)$redeem['customer_user_id'],$orderId,$points]);
}
