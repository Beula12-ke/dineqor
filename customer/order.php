<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['customer']);
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$q = db()->prepare('SELECT o.*,r.name AS restaurant_name,r.slug AS restaurant_slug,r.phone AS restaurant_phone FROM orders o LEFT JOIN restaurants r ON r.id=o.restaurant_id WHERE o.id=? AND o.customer_user_id=? LIMIT 1');
$q->execute([$orderId, (int)$u['id']]); $order = $q->fetch();
if (!$order) { http_response_code(404); page_head('Order not found'); nav_bar($u); echo '<main class="customer-home customer-workspace"><div class="empty"><b>We couldn’t find that order</b>It may have been removed or belong to another account.<a href="' . e(url('customer/orders.php')) . '">Back to my orders</a></div></main>'; page_foot(); exit; }
$itemsQ = db()->prepare('SELECT product_name,variation_name,quantity,unit_price,line_total,addons_json,notes FROM order_items WHERE order_id=? ORDER BY id');
$itemsQ->execute([(int)$order['id']]); $items = $itemsQ->fetchAll();
$isDelivery = $order['order_type'] === 'delivery';
$steps = ['new' => 'Order received', 'confirmed' => 'Confirmed', 'preparing' => 'Being prepared', 'ready' => $isDelivery ? 'Ready for dispatch' : 'Ready for collection'];
if ($isDelivery) { $steps['out_for_delivery'] = 'On the way'; $steps['delivered'] = 'Delivered'; } else { $steps['completed'] = 'Completed'; }
$stepKeys = array_keys($steps); $position = array_search($order['status'], $stepKeys, true);
if ($position === false && $isDelivery && $order['status'] === 'completed') $position = array_search('delivered', $stepKeys, true);
if ($position === false && !$isDelivery && $order['status'] === 'delivered') $position = array_search('completed', $stepKeys, true);
$isCancelled = $order['status'] === 'cancelled';
$prepQ = db()->prepare('SELECT MAX(COALESCE(p.preparation_time,0)) FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?');
$prepQ->execute([(int)$order['id']]); $prepMinutes=(int)$prepQ->fetchColumn(); if($prepMinutes<1)$prepMinutes=25;
$estimatedReadyAt=date('Y-m-d H:i:s',strtotime($order['created_at'])+$prepMinutes*60);
$isActive=!$isCancelled&&!in_array($order['status'],['completed','delivered'],true);
$fmtMoney = static fn(float|int $amount): string => 'KSh ' . number_format((float)$amount, 0);
page_head('Order ' . $order['order_number']); nav_bar($u); ?>
<main class="customer-home customer-workspace customer-list-page order-detail-page">
  <a class="order-back-link" href="<?= e(url('customer/orders.php')) ?>">← Back to my orders</a>
  <header class="customer-heading"><div><div class="eyebrow">ORDER DETAILS</div><h1>Order #<?= e($order['order_number']) ?></h1><p><?= e($order['restaurant_name'] ?: 'Restaurant') ?> · <?= e(date('M j, Y · g:i A', strtotime($order['created_at']))) ?></p></div><div class="order-heading-actions"><span class="order-status <?= e($order['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $order['status']))) ?></span><button class="btn sm reorder-button" type="button" data-reorder-id="<?= (int)$order['id'] ?>">Order again</button></div></header>
  <div class="msg reorder-message" data-reorder-message role="status"></div>
  <div class="order-detail-grid"><div class="order-detail-main">
    <section class="dash-panel order-progress" id="customerOrderTracker" data-order-id="<?= (int)$order['id'] ?>" data-order-type="<?= e($order['order_type']) ?>" data-current-status="<?= e($order['status']) ?>" data-api="<?= e(url('api/customer/order-status.php')) ?>">
      <div class="panel-heading"><div><h2><?= $isCancelled ? 'Order cancelled' : 'Order progress' ?></h2><p data-order-live-status aria-live="polite"><?= $isCancelled ? 'This order is no longer being prepared.' : 'Status refreshes automatically while your order is active.' ?></p></div><span class="tracker-live<?= $isActive?' is-active':'' ?>" data-order-live-indicator><?= $isActive?'LIVE':'UPDATED' ?></span></div>
      <?php if ($isCancelled): ?><div class="order-cancel-note" data-order-cancel-note><?= e($order['cancellation_reason'] ?: 'The restaurant cancelled this order.') ?></div>
      <?php else: ?><div class="order-eta" data-order-eta<?= $isActive?'':' hidden' ?>><span>Estimated ready</span><b data-order-eta-time><?= e(date('g:i A',strtotime($estimatedReadyAt))) ?></b><small data-order-eta-remaining></small></div><ol class="order-timeline" data-order-timeline><?php foreach ($steps as $key => $label): $done = $position !== false && $position !== null && array_search($key, $stepKeys, true) <= $position; ?><li data-order-step="<?= e($key) ?>" class="<?= $done ? 'done' : '' ?>"><span class="timeline-dot"></span><span><?= e($label) ?></span></li><?php endforeach ?></ol><div class="order-cancel-note" data-order-cancel-note hidden></div><?php endif ?>
    </section>
    <section class="dash-panel order-detail-items"><div class="panel-heading"><div><h2>Items ordered</h2><p><?= number_format(count($items)) ?> line items</p></div></div>
      <?php foreach ($items as $item): $addons = $item['addons_json'] ? json_decode($item['addons_json'], true) : []; ?><article class="detail-item"><span class="detail-item-qty"><?= number_format((int)$item['quantity']) ?>×</span><div class="detail-item-copy"><b><?= e($item['product_name']) ?></b><?php if ($item['variation_name']): ?><small><?= e($item['variation_name']) ?></small><?php endif ?><?php if ($addons): ?><small><?= e(implode(' · ', array_map(static fn($addon) => (($addon['qty'] ?? 1) . '× ' . ($addon['name'] ?? 'Extra')), $addons))) ?></small><?php endif ?><?php if ($item['notes']): ?><small>Note: <?= e($item['notes']) ?></small><?php endif ?></div><span class="detail-item-price"><?= e($fmtMoney($item['line_total'])) ?></span></article><?php endforeach ?>
    </section>
  </div><aside class="dash-panel order-summary"><h2>Order summary</h2><div class="sum"><span>Order type</span><b><?= e(ucwords(str_replace('_', ' ', $order['order_type']))) ?></b></div><div class="sum"><span>Subtotal</span><b><?= e($fmtMoney($order['subtotal'])) ?></b></div><?php if ((float)$order['discount_amount'] > 0): ?><div class="sum"><span>Discount</span><b>−<?= e($fmtMoney($order['discount_amount'])) ?></b></div><?php endif ?><?php if ((float)$order['delivery_fee'] > 0): ?><div class="sum"><span>Delivery</span><b><?= e($fmtMoney($order['delivery_fee'])) ?></b></div><?php endif ?><div class="sum big"><span>Total</span><b><?= e($fmtMoney($order['total_amount'])) ?></b></div>
    <div class="summary-divider"></div><div class="sum"><span>Payment</span><b data-order-payment-status><?= e(ucfirst($order['payment_status'])) ?></b></div><p class="payment-note" data-order-payment-note><?= e($order['payment_method']==='mpesa' ? ($order['payment_status']==='paid' ? 'M-Pesa payment confirmed.' : ($order['payment_status']==='failed' ? 'M-Pesa payment did not complete. Contact the restaurant.' : 'Waiting for M-Pesa confirmation.')) : ucfirst($order['payment_method']).($order['payment_status']==='paid'?' · paid':' · payment due')) ?></p>
    <?php if ($order['delivery_notes']): ?><div class="summary-divider"></div><h3>Delivery details</h3><p class="delivery-note"><?= nl2br(e($order['delivery_notes'])) ?></p><?php endif ?>
    <?php if ($order['special_instructions']): ?><div class="summary-divider"></div><h3>Instructions</h3><p class="delivery-note"><?= e($order['special_instructions']) ?></p><?php endif ?>
    <div class="summary-divider"></div><h3>Restaurant</h3><p class="delivery-note"><?php if ($order['restaurant_slug']): ?><a href="<?= e(url('restaurant/' . rawurlencode($order['restaurant_slug']))) ?>"><?= e($order['restaurant_name']) ?></a><?php else: ?><?= e($order['restaurant_name'] ?: 'Restaurant') ?><?php endif ?><?php if ($order['restaurant_phone']): ?><br><a href="tel:<?= e($order['restaurant_phone']) ?>"><?= e($order['restaurant_phone']) ?></a><?php endif ?></p>
  </aside></div>
</main>
<?php page_foot('customer-order-tracker.js','customer-reorder.js');
