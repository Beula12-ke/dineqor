<?php
require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['customer']);
$q = db()->prepare("SELECT o.*,r.name AS restaurant_name,r.slug AS restaurant_slug,r.phone AS restaurant_phone,
    (SELECT MAX(COALESCE(p.preparation_time,0)) FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=o.id) AS prep_minutes
    FROM orders o LEFT JOIN restaurants r ON r.id=o.restaurant_id
    WHERE o.customer_user_id=? ORDER BY o.created_at DESC LIMIT 100");
$q->execute([(int)$u['id']]);
$orders = $q->fetchAll();
$itemsByOrder = [];
if ($orders) {
    $orderIds = array_map(static fn(array $order): int => (int)$order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsQ = db()->prepare("SELECT order_id,product_name,variation_name,quantity,line_total,addons_json,notes FROM order_items WHERE order_id IN ($placeholders) ORDER BY id");
    $itemsQ->execute($orderIds);
    foreach ($itemsQ->fetchAll() as $item) $itemsByOrder[(int)$item['order_id']][] = $item;
}
$fmtMoney = static fn(float|int|string|null $amount): string => 'KSh ' . number_format((float)$amount, 0);
$statusLabel = static fn(string $status): string => match ($status) {
    'new' => 'Order received', 'confirmed' => 'Confirmed', 'preparing' => 'Being prepared',
    'ready' => 'Ready', 'out_for_delivery' => 'On the way', 'delivered' => 'Delivered',
    'completed' => 'Completed', 'cancelled' => 'Cancelled', default => ucwords(str_replace('_', ' ', $status)),
};
$paymentNote = static function (string $method, string $status): string {
    if ($method === 'mpesa') return match ($status) {
        'paid' => 'M-Pesa payment confirmed.', 'failed' => 'M-Pesa payment did not complete. Contact the restaurant.',
        'refunded' => 'M-Pesa payment refunded.', default => 'Waiting for M-Pesa confirmation.',
    };
    return ucfirst($method) . match ($status) {
        'paid' => ' · paid', 'refunded' => ' · refunded', default => ' · payment due',
    };
};
page_head('My orders'); nav_bar($u); ?>
<main class="customer-home customer-workspace customer-list-page">
  <header class="customer-heading"><div><div class="eyebrow">YOUR ACCOUNT</div><h1>My orders</h1><p>Track progress and see all the details of your recent orders.</p></div><a class="customer-browse" href="<?= e(url()) ?>">Browse restaurants <span>→</span></a></header>
  <section class="dash-panel customer-panel customer-orders-history"><div class="panel-heading"><div><h2>Order history</h2><p><?= number_format(count($orders)) ?> most recent orders</p></div></div>
    <?php if ($orders): ?><div class="customer-order-list"><?php foreach ($orders as $order):
      $delivery = $order['order_type'] === 'delivery';
      $tableService = in_array($order['order_type'], ['qr_table', 'dine_in'], true);
      $steps = $delivery
        ? ['new' => 'Received', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready' => 'Ready', 'out_for_delivery' => 'On the way', 'delivered' => 'Delivered']
        : ['new' => 'Received', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready' => $tableService ? 'Ready to serve' : 'Ready for collection', 'completed' => 'Completed'];
      $current = $order['status'];
      if ($delivery && $current === 'completed') $current = 'delivered';
      if (!$delivery && $current === 'delivered') $current = 'completed';
      $currentIndex = array_search($current, array_keys($steps), true);
      $isCancelled = $order['status'] === 'cancelled';
      $orderItems = $itemsByOrder[(int)$order['id']] ?? [];
      $prepMinutes = (int)($order['prep_minutes'] ?? 0);
      if ($prepMinutes < 1) $prepMinutes = 25;
      $estimatedReady = date('g:i A', strtotime($order['created_at']) + $prepMinutes * 60);
      $paymentStatus = $order['payment_status'] ?? 'unpaid';
      $paymentMethod = $order['payment_method'] ?? 'cash';
      ?><article class="customer-order-card customer-order" data-order-id="<?= (int)$order['id'] ?>" data-current-status="<?= e($order['status']) ?>">
        <header class="customer-order-card-head">
          <span class="customer-order-mark" aria-hidden="true">▤</span>
          <div class="customer-order-main"><b><?= e($order['restaurant_name'] ?: 'Restaurant') ?></b><small>Order #<?= e($order['order_number']) ?> <span>·</span> <?= e(ucwords(str_replace('_', ' ', $order['order_type']))) ?> <span>·</span> <?= e(date('M j, Y · g:i A', strtotime($order['created_at']))) ?></small></div>
          <span class="order-status <?= e($order['status']) ?>" data-order-status><?= e($statusLabel($order['status'])) ?></span>
        </header>
        <div class="customer-order-card-content">
          <section class="customer-order-items" aria-label="Items ordered">
            <div class="customer-order-section-heading"><h3>Items ordered</h3><span><?= number_format(count($orderItems)) ?> <?= count($orderItems) === 1 ? 'item' : 'items' ?></span></div>
            <?php if ($orderItems): foreach ($orderItems as $item): $addons = $item['addons_json'] ? json_decode($item['addons_json'], true) : []; if (!is_array($addons)) $addons = []; ?>
              <div class="customer-order-item"><span class="customer-order-item-qty"><?= number_format((int)$item['quantity']) ?>×</span><div class="customer-order-item-copy"><b><?= e($item['product_name']) ?></b><?php if ($item['variation_name']): ?><small><?= e($item['variation_name']) ?></small><?php endif ?><?php if ($addons): ?><small><?= e(implode(' · ', array_map(static fn($addon) => (($addon['qty'] ?? 1) . '× ' . ($addon['name'] ?? 'Extra')), $addons))) ?></small><?php endif ?><?php if ($item['notes']): ?><small>Note: <?= e($item['notes']) ?></small><?php endif ?></div><strong><?= e($fmtMoney($item['line_total'])) ?></strong></div>
            <?php endforeach; else: ?><p class="customer-order-no-items">Item information is unavailable for this order.</p><?php endif ?>
          </section>
          <aside class="customer-order-summary" aria-label="Order summary">
            <div class="customer-order-section-heading"><h3>Summary</h3></div>
            <div class="customer-order-sum"><span>Subtotal</span><b><?= e($fmtMoney($order['subtotal'])) ?></b></div>
            <?php if ((float)$order['discount_amount'] > 0): ?><div class="customer-order-sum"><span>Discount</span><b>−<?= e($fmtMoney($order['discount_amount'])) ?></b></div><?php endif ?>
            <?php if ((float)$order['delivery_fee'] > 0): ?><div class="customer-order-sum"><span>Delivery</span><b><?= e($fmtMoney($order['delivery_fee'])) ?></b></div><?php endif ?>
            <div class="customer-order-sum customer-order-grand-total"><span>Total</span><b><?= e($fmtMoney($order['total_amount'])) ?></b></div>
            <div class="customer-order-payment"><span>Payment</span><div><b data-order-payment-status><?= e(ucfirst($paymentStatus)) ?></b><small data-order-payment-note><?= e($paymentNote($paymentMethod, $paymentStatus)) ?></small></div></div>
          </aside>
        </div>
        <?php if (($delivery && $order['delivery_notes']) || $order['special_instructions']): ?><div class="customer-order-notes">
          <?php if ($delivery && $order['delivery_notes']): ?><div><b>Delivery details</b><p><?= nl2br(e($order['delivery_notes'])) ?></p></div><?php endif ?>
          <?php if ($order['special_instructions']): ?><div><b>Special instructions</b><p><?= e($order['special_instructions']) ?></p></div><?php endif ?>
        </div><?php endif ?>
        <footer class="customer-order-card-footer"><div class="customer-order-restaurant"><span>Restaurant</span><b><?php if ($order['restaurant_slug']): ?><a href="<?= e(url('restaurant/' . rawurlencode($order['restaurant_slug']))) ?>"><?= e($order['restaurant_name'] ?: 'Restaurant') ?></a><?php else: ?><?= e($order['restaurant_name'] ?: 'Restaurant') ?><?php endif ?></b><?php if ($order['restaurant_phone']): ?><a href="tel:<?= e($order['restaurant_phone']) ?>"><?= e($order['restaurant_phone']) ?></a><?php endif ?></div></footer>
        <?php if ($isCancelled): ?><div class="customer-order-cancelled" data-order-cancelled>Order cancelled<?= $order['cancellation_reason'] ? ' · ' . e($order['cancellation_reason']) : '' ?></div>
        <?php else: ?><section class="customer-order-progress-block" aria-label="Order progress"><div class="customer-order-progress-heading"><div><h3>Order progress</h3><p data-order-estimate<?= in_array($order['status'], ['completed','delivered'], true) ? ' hidden' : '' ?>>Estimated ready around <?= e($estimatedReady) ?></p></div><span class="customer-order-updated">UPDATES AUTOMATICALLY</span></div><div class="customer-order-progress" data-order-progress data-order-type="<?= e($order['order_type']) ?>" data-current-status="<?= e($order['status']) ?>" aria-label="Order progress: <?= e($statusLabel($order['status'])) ?>"><ol><?php foreach ($steps as $key => $label): $stepIndex = array_search($key, array_keys($steps), true); $done = $currentIndex !== false && $stepIndex <= $currentIndex; ?><li data-progress-step="<?= e($key) ?>" class="<?= $done ? 'done' : '' ?><?= $key === $current ? ' current' : '' ?>"<?= $key === $current ? ' aria-current="step"' : '' ?>><i></i><span><?= e($label) ?></span></li><?php endforeach ?></ol></div></section><?php endif ?>
      </article><?php endforeach ?></div>
    <?php else: ?><div class="dash-empty"><span>♡</span><b>No orders yet</b><p>Your orders will appear here once you find something delicious.</p><a class="panel-link" href="<?= e(url()) ?>">Browse restaurants →</a></div><?php endif ?>
  </section>
</main>
<?php page_foot('customer-orders-progress.js');
