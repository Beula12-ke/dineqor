<?php
// Prepare an authenticated customer's past order for a new cart, checking current menu availability and prices.
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_error('Method not allowed.', 405);
$u = require_user_type('customer');
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($orderId < 1) json_error('Order not found.', 404);
$pdo = db();
$orderQ = $pdo->prepare('SELECT o.id,r.slug,r.name AS restaurant_name,r.status AS restaurant_status,r.deleted_at
    FROM orders o JOIN restaurants r ON r.id=o.restaurant_id
    WHERE o.id=? AND o.customer_user_id=? LIMIT 1');
$orderQ->execute([$orderId,(int)$u['id']]); $order = $orderQ->fetch();
if (!$order) json_error('Order not found.', 404);
if ($order['restaurant_status'] !== 'active' || $order['deleted_at'] !== null) json_error('This restaurant is not accepting orders right now.', 409);

$itemsQ = $pdo->prepare('SELECT oi.product_id,oi.variation_id,oi.product_name,oi.variation_name,oi.quantity,oi.addons_json,oi.notes,
        p.name AS current_name,p.price,p.track_stock,p.stock_quantity,p.is_active,p.is_available,p.deleted_at
    FROM order_items oi JOIN orders o ON o.id=oi.order_id
    LEFT JOIN products p ON p.id=oi.product_id AND p.restaurant_id=o.restaurant_id
    WHERE oi.order_id=? ORDER BY oi.id');
$itemsQ->execute([$orderId]); $oldItems = $itemsQ->fetchAll();
$lines=[]; $unavailable=[];
foreach ($oldItems as $old) {
    $productId=(int)($old['product_id'] ?? 0); $qty=(int)$old['quantity'];
    if (!$productId || !$old['current_name'] || !(int)$old['is_active'] || !(int)$old['is_available'] || $old['deleted_at'] !== null ||
        ((int)$old['track_stock'] && $old['stock_quantity'] !== null && (float)$old['stock_quantity'] < $qty)) {
        $unavailable[]=$old['product_name']; continue;
    }

    $variationId=(int)($old['variation_id'] ?? 0); $variation=null;
    if ($variationId) {
        $variationQ=$pdo->prepare('SELECT id,name,price_delta FROM product_variations WHERE id=? AND product_id=? AND is_available=1 LIMIT 1');
        $variationQ->execute([$variationId,$productId]); $variation=$variationQ->fetch();
        if (!$variation) { $unavailable[]=$old['product_name'] . ' (option no longer available)'; continue; }
    }
    $savedAddons=$old['addons_json'] ? json_decode($old['addons_json'],true) : [];
    $addons=[]; $addonsInvalid=false;
    foreach ((array)$savedAddons as $saved) {
        $addonId=(int)($saved['id'] ?? 0); $addonQty=(int)($saved['qty'] ?? 0);
        if (!$addonId || $addonQty<1) continue;
        $addonQ=$pdo->prepare('SELECT id,name,price,max_qty FROM product_addons WHERE id=? AND product_id=? AND is_available=1 LIMIT 1');
        $addonQ->execute([$addonId,$productId]); $addon=$addonQ->fetch();
        if (!$addon || $addonQty>(int)$addon['max_qty']) { $addonsInvalid=true; break; }
        $addons[]=['id'=>(int)$addon['id'],'name'=>$addon['name'],'price'=>(float)$addon['price'],'qty'=>$addonQty];
    }
    if ($addonsInvalid) { $unavailable[]=$old['product_name'] . ' (an extra is no longer available)'; continue; }
    $line=['pid'=>$productId,'name'=>$old['current_name'],'unit'=>(float)$old['price']+($variation?(float)$variation['price_delta']:0),
        'vid'=>$variation?(int)$variation['id']:null,'vname'=>$variation?$variation['name']:null,'addons'=>$addons,
        'qty'=>min(50,$qty),'notes'=>$old['notes'] ?: ''];
    $line['key']=implode('|',[$line['pid'],$line['vid'] ?: '',json_encode(array_map(static fn($a)=>[$a['id'],$a['qty']],$line['addons'])),$line['notes']]);
    $lines[]=$line;
}
if (!$lines) json_error('None of the items from that order are currently available.',409,['unavailable'=>$unavailable]);
json_out(['ok'=>true,'restaurant_slug'=>$order['slug'],'restaurant_name'=>$order['restaurant_name'],'lines'=>$lines,'unavailable'=>$unavailable]);
