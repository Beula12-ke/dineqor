<?php
// Waiter service desk: table service, table orders, handoff and table transfer.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orders.php';
$u = require_permission('view_orders');
$rid = tenant_id($u);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tableQ = $pdo->prepare("SELECT t.id,t.table_number,t.label,t.capacity,t.section,t.status,
        (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id=t.restaurant_id AND o.table_id=t.id
            AND o.status NOT IN ('completed','delivered','cancelled')) AS open_orders
        FROM restaurant_tables t WHERE t.restaurant_id=? ORDER BY t.table_number");
    $tableQ->execute([$rid]);
    $readyQ = $pdo->prepare("SELECT o.id,o.order_number,o.order_type,o.status,o.customer_name,o.special_instructions,o.created_at,
        t.table_number,t.label AS table_label,o.total_amount
        FROM orders o JOIN restaurant_tables t ON t.id=o.table_id AND t.restaurant_id=o.restaurant_id
        WHERE o.restaurant_id=? AND o.status='ready' AND o.order_type IN ('dine_in','qr_table')
        ORDER BY o.ready_at ASC,o.created_at ASC LIMIT 100");
    $readyQ->execute([$rid]);
    $ready = $readyQ->fetchAll();
    $items = [];
    if ($ready) {
        $ids = array_column($ready, 'id'); $in = implode(',', array_fill(0, count($ids), '?'));
        $itemQ = $pdo->prepare("SELECT order_id,product_name,variation_name,quantity,addons_json,notes FROM order_items WHERE order_id IN ($in) ORDER BY id");
        $itemQ->execute($ids);
        foreach ($itemQ->fetchAll() as $item) {
            $addons = $item['addons_json'] ? json_decode($item['addons_json'], true) : [];
            $items[(int)$item['order_id']][] = ['name'=>$item['product_name'],'variation'=>$item['variation_name'],
                'qty'=>(int)$item['quantity'],'addons'=>array_map(fn($a)=>$a['qty'].'× '.$a['name'],$addons ?: []),'notes'=>$item['notes']];
        }
    }
    foreach ($ready as &$order) { $order['id']=(int)$order['id']; $order['total_amount']=(float)$order['total_amount']; $order['items']=$items[$order['id']] ?? []; }
    unset($order);

    $cats = $pdo->prepare('SELECT id,name FROM categories WHERE restaurant_id=? AND is_active=1 AND deleted_at IS NULL ORDER BY sort_order,name');
    $cats->execute([$rid]);
    $productQ = $pdo->prepare('SELECT id,category_id,name,price FROM products WHERE restaurant_id=? AND is_active=1 AND is_available=1 AND deleted_at IS NULL ORDER BY sort_order,name LIMIT 500');
    $productQ->execute([$rid]); $products = $productQ->fetchAll();
    $pids = array_column($products,'id'); $variations=[];
    if ($pids) {
        $in = implode(',',array_fill(0,count($pids),'?'));
        $vq=$pdo->prepare("SELECT id,product_id,name,price_delta FROM product_variations WHERE product_id IN ($in) AND is_available=1 ORDER BY sort_order,id");
        $vq->execute($pids);
        foreach ($vq->fetchAll() as $v) $variations[(int)$v['product_id']][]=['id'=>(int)$v['id'],'name'=>$v['name'],'delta'=>(float)$v['price_delta']];
    }
    foreach ($products as &$product) { $product['id']=(int)$product['id']; $product['category_id']=(int)$product['category_id']; $product['price']=(float)$product['price']; $product['variations']=$variations[$product['id']] ?? []; }
    unset($product);
    json_out(['ok'=>true,'csrf'=>csrf_token(),'tables'=>$tableQ->fetchAll(),'ready_orders'=>$ready,
        'categories'=>$cats->fetchAll(),'products'=>$products]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.',405);
start_secure_session(); require_csrf();
$in=json_input(); $action=(string)($in['action'] ?? '');

if ($action === 'create_order') {
    if (!has_permission($u,'manage_orders')) json_error('You do not have permission to send orders.',403);
    $tableId=filter_var($in['table_id'] ?? null,FILTER_VALIDATE_INT);
    if (!$tableId || $tableId < 1) json_error('Choose a table.',422,['fields'=>['table_id'=>'Choose a table.']]);
    $cart=price_cart($rid,(array)($in['items'] ?? []));
    $name=clean_str($in['customer_name'] ?? '',150) ?: null;
    $phone=clean_str($in['customer_phone'] ?? '',30) ?: null;
    $notes=clean_str($in['instructions'] ?? '',1000) ?: null;
    try {
        $pdo->beginTransaction();
        $tableQ=$pdo->prepare("SELECT id FROM restaurant_tables WHERE id=? AND restaurant_id=? AND status NOT IN ('closed','cleaning') FOR UPDATE");
        $tableQ->execute([$tableId,$rid]);
        if (!$tableQ->fetch()) { $pdo->rollBack(); json_error('That table is unavailable. Refresh and choose another table.',409); }
        $stockQ=$pdo->prepare('SELECT track_stock,stock_quantity FROM products WHERE id=? AND restaurant_id=? FOR UPDATE');
        $stockUpdate=$pdo->prepare('UPDATE products SET stock_quantity=stock_quantity-? WHERE id=? AND restaurant_id=? AND track_stock=1 AND stock_quantity IS NOT NULL AND stock_quantity>=?');
        foreach ($cart['stock'] as $productId=>$qty) {
            $stockQ->execute([$productId,$rid]); $stock=$stockQ->fetch();
            if ($stock && $stock['track_stock'] && $stock['stock_quantity'] !== null) {
                $stockUpdate->execute([$qty,$productId,$rid,$qty]);
                if (!$stockUpdate->rowCount()) { $pdo->rollBack(); json_error('An item just sold out. Please review the order.',409); }
            }
        }
        $prefix=date('ymd'); $orderId=0; $number='';
        $insert=$pdo->prepare("INSERT INTO orders (restaurant_id,customer_user_id,table_id,order_number,order_type,source,status,
            subtotal,total_amount,payment_status,payment_method,customer_name,customer_phone,special_instructions,staff_user_id)
            VALUES (?,NULL,?,?,'dine_in','waiter','new',?,?,'unpaid','cash',?,?,?,?)");
        for ($try=0;$try<6 && !$orderId;$try++) {
            $max=$pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(order_number,'-',-1) AS UNSIGNED)) FROM orders WHERE restaurant_id=? AND order_number LIKE ?");
            $max->execute([$rid,$prefix.'-%']); $number=sprintf('%s-%03d',$prefix,(int)$max->fetchColumn()+1+$try);
            try { $insert->execute([$rid,$tableId,$number,$cart['subtotal'],$cart['subtotal'],$name,$phone,$notes,(int)$u['id']]); $orderId=(int)$pdo->lastInsertId(); }
            catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }
        }
        if (!$orderId) throw new RuntimeException('Could not allocate order number.');
        $lineQ=$pdo->prepare('INSERT INTO order_items (order_id,product_id,variation_id,product_name,variation_name,unit_price,quantity,line_total,addons_json,addons_total,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($cart['rows'] as $row) $lineQ->execute([$orderId,$row['product_id'],$row['variation_id'],$row['product_name'],$row['variation_name'],$row['unit_price'],$row['quantity'],$row['line_total'],$row['addons_json'],$row['addons_total'],$row['notes']]);
        $recipeError=consume_recipe_inventory($pdo,$rid,$cart['rows'],$orderId,(int)$u['id']);
        if ($recipeError) { $pdo->rollBack(); json_error($recipeError,409); }
        $pdo->prepare("INSERT INTO payments (restaurant_id,order_id,amount,method,status) VALUES (?,?,?,'cash','pending')")->execute([$rid,$orderId,$cart['subtotal']]);
        $pdo->prepare("INSERT INTO order_status_history (order_id,status,changed_by,notes) VALUES (?,'new',?,'Waiter sent order to kitchen')")->execute([$orderId,(int)$u['id']]);
        $pdo->prepare("UPDATE restaurant_tables SET status='ordering' WHERE id=? AND restaurant_id=?")->execute([$tableId,$rid]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); error_log('waiter create order: '.$e->getMessage()); json_error('Could not send this order. Please try again.',500);
    }
    log_activity('order.waiter_created',(int)$u['id'],$rid,'order',$orderId,"Waiter order $number for table $tableId");
    json_out(['ok'=>true,'message'=>'Order sent to the kitchen.','order_number'=>$number],201);
}

if ($action === 'serve') {
    if (!has_permission($u,'manage_orders')) json_error('You do not have permission to serve orders.',403);
    $orderId=filter_var($in['order_id'] ?? null,FILTER_VALIDATE_INT);
    if (!$orderId || $orderId < 1) json_error('Order not found.',422);
    try {
        $pdo->beginTransaction();
        $q=$pdo->prepare("SELECT id,table_id,payment_status,payment_method FROM orders WHERE id=? AND restaurant_id=? AND status='ready' AND order_type IN ('dine_in','qr_table') FOR UPDATE");
        $q->execute([$orderId,$rid]); $order=$q->fetch();
        if (!$order) { $pdo->rollBack(); json_error('This order is no longer ready to serve.',409); }
        if ($order['payment_method']==='mpesa' && $order['payment_status']!=='paid') { $pdo->rollBack(); json_error('Wait for Safaricom to confirm this M-Pesa payment before serving.',409); }
        $pdo->prepare("UPDATE orders SET status='completed',completed_at=NOW() WHERE id=? AND restaurant_id=?")->execute([$orderId,$rid]);
        $pdo->prepare("INSERT INTO order_status_history (order_id,status,changed_by,notes) VALUES (?,'completed',?,'Served by waiter')")->execute([$orderId,(int)$u['id']]);
        notify_customer_order_status($pdo,(int)$orderId,'completed');
        if ($order['payment_method']==='cash' && $order['payment_status']!=='paid') {
            $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND restaurant_id=?")->execute([$orderId,$rid]);
            $pdo->prepare("UPDATE payments SET status='completed',paid_at=NOW() WHERE order_id=? AND restaurant_id=? AND status='pending'")->execute([$orderId,$rid]);
        }
        award_order_loyalty($pdo,$rid,(int)$orderId);
        if ($order['table_id']) {
            $left=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE restaurant_id=? AND table_id=? AND status NOT IN ('completed','delivered','cancelled')");
            $left->execute([$rid,$order['table_id']]);
            if ((int)$left->fetchColumn()===0) $pdo->prepare("UPDATE restaurant_tables SET status='cleaning' WHERE id=? AND restaurant_id=?")->execute([$order['table_id'],$rid]);
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('waiter serve: '.$e->getMessage()); json_error('Could not complete service for this order.',500); }
    log_activity('order.served',(int)$u['id'],$rid,'order',(int)$orderId);
    json_out(['ok'=>true,'message'=>'Order marked served.']);
}

if ($action === 'pickup') {
    if (!has_permission($u,'manage_orders')) json_error('You do not have permission to update orders.',403);
    $orderId=filter_var($in['order_id'] ?? null,FILTER_VALIDATE_INT);
    if (!$orderId || $orderId < 1) json_error('Order not found.',422);
    try {
        $pdo->beginTransaction();
        $q=$pdo->prepare("SELECT id FROM orders WHERE id=? AND restaurant_id=? AND status='ready' AND order_type IN ('dine_in','qr_table') FOR UPDATE");
        $q->execute([$orderId,$rid]);
        if (!$q->fetch()) { $pdo->rollBack(); json_error('This order is not waiting for pickup.',409); }
        $done=$pdo->prepare("SELECT COUNT(*) FROM order_status_history WHERE order_id=? AND notes='Picked up by waiter'");
        $done->execute([$orderId]);
        if ((int)$done->fetchColumn()>0) { $pdo->rollBack(); json_out(['ok'=>true,'message'=>'Already marked as picked up.']); }
        $pdo->prepare("INSERT INTO order_status_history (order_id,status,changed_by,notes) VALUES (?,'ready',?,'Picked up by waiter')")->execute([$orderId,(int)$u['id']]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('waiter pickup: '.$e->getMessage()); json_error('Could not record the pickup.',500); }
    log_activity('order.picked_up',(int)$u['id'],$rid,'order',(int)$orderId);
    json_out(['ok'=>true,'message'=>'Kitchen has been told this order was picked up.']);
}

if ($action === 'handover') {
    if (!has_permission($u,'manage_orders')) json_error('You do not have permission to update orders.',403);
    $orderId=filter_var($in['order_id'] ?? null,FILTER_VALIDATE_INT);
    if (!$orderId || $orderId < 1) json_error('Order not found.',422);
    try {
        $pdo->beginTransaction();
        $q=$pdo->prepare("SELECT id,payment_status FROM orders WHERE id=? AND restaurant_id=? AND status='ready' AND order_type IN ('pickup','takeaway') FOR UPDATE");
        $q->execute([$orderId,$rid]); $order=$q->fetch();
        if (!$order) { $pdo->rollBack(); json_error('This order is not waiting for handover.',409); }
        if ($order['payment_status']!=='paid') { $pdo->rollBack(); json_error('This order is not paid yet. The customer pays at the counter first.',409); }
        $pdo->prepare("UPDATE orders SET status='completed',completed_at=NOW() WHERE id=? AND restaurant_id=?")->execute([$orderId,$rid]);
        $pdo->prepare("INSERT INTO order_status_history (order_id,status,changed_by,notes) VALUES (?,'completed',?,'Handed to customer')")->execute([$orderId,(int)$u['id']]);
        try { notify_customer_order_status($pdo,(int)$orderId,'completed'); } catch (Throwable $notificationError) { error_log('customer order notification was not saved: '.$notificationError->getMessage()); }
        award_order_loyalty($pdo,$rid,(int)$orderId);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('waiter handover: '.$e->getMessage()); json_error('Could not complete the handover.',500); }
    log_activity('order.handed_over',(int)$u['id'],$rid,'order',(int)$orderId);
    json_out(['ok'=>true,'message'=>'Order handed to the customer.']);
}

if ($action === 'close_table') {
    if (!has_permission($u,'manage_tables')) json_error('You do not have permission to close tables.',403);
    $tableId=filter_var($in['table_id'] ?? null,FILTER_VALIDATE_INT);
    if (!$tableId || $tableId < 1) json_error('Choose a valid table.',422);
    try {
        $pdo->beginTransaction();
        $lock=$pdo->prepare("SELECT id FROM restaurant_tables WHERE id=? AND restaurant_id=? AND status <> 'closed' FOR UPDATE");
        $lock->execute([$tableId,$rid]);
        if (!$lock->fetch()) { $pdo->rollBack(); json_error('Table not found or closed.',404); }
        $open=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE restaurant_id=? AND table_id=? AND status NOT IN ('completed','delivered','cancelled')");
        $open->execute([$rid,$tableId]);
        if ((int)$open->fetchColumn()>0) { $pdo->rollBack(); json_error('Serve or finish every open order before closing the table.',409); }
        $pdo->prepare("UPDATE restaurant_tables SET status='available' WHERE id=? AND restaurant_id=?")->execute([$tableId,$rid]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('waiter close table: '.$e->getMessage()); json_error('Could not reset this table.',500); }
    log_activity('table.closed_by_waiter',(int)$u['id'],$rid,'table',(int)$tableId);
    json_out(['ok'=>true,'message'=>'Table is available again.']);
}

if ($action === 'transfer_table') {
    if (!has_permission($u,'manage_tables')) json_error('You do not have permission to transfer tables.',403);
    $from=filter_var($in['from_table_id'] ?? null,FILTER_VALIDATE_INT); $to=filter_var($in['to_table_id'] ?? null,FILTER_VALIDATE_INT);
    if (!$from || !$to || $from===$to) json_error('Choose two different tables.',422);
    try {
        $pdo->beginTransaction();
        $lock=$pdo->prepare("SELECT id,status FROM restaurant_tables WHERE restaurant_id=? AND id IN (?,?) ORDER BY id FOR UPDATE");
        $lock->execute([$rid,$from,$to]); $locked=$lock->fetchAll();
        if (count($locked)!==2) { $pdo->rollBack(); json_error('One of those tables was not found.',404); }
        foreach ($locked as $table) if ((int)$table['id']===$to && in_array($table['status'],['closed','cleaning'],true)) { $pdo->rollBack(); json_error('The destination table is not available for service.',409); }
        $move=$pdo->prepare("UPDATE orders SET table_id=? WHERE restaurant_id=? AND table_id=? AND status NOT IN ('completed','delivered','cancelled')");
        $move->execute([$to,$rid,$from]);
        if (!$move->rowCount()) { $pdo->rollBack(); json_error('The starting table has no open orders to transfer.',409); }
        $pdo->prepare("UPDATE restaurant_tables SET status='cleaning' WHERE id=? AND restaurant_id=?")->execute([$from,$rid]);
        $pdo->prepare("UPDATE restaurant_tables SET status='occupied' WHERE id=? AND restaurant_id=?")->execute([$to,$rid]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('waiter transfer: '.$e->getMessage()); json_error('Could not transfer this table.',500); }
    log_activity('table.transferred',(int)$u['id'],$rid,'table',(int)$from,'Transferred service to table '.$to);
    json_out(['ok'=>true,'message'=>'Orders and service have moved to the new table.']);
}

json_error('Invalid request.',422);
