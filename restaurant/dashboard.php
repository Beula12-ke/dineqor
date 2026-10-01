<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
if ($u['user_type'] === 'restaurant_staff' && !has_permission($u, 'view_reports')) { header('Location: ' . url('staff/dashboard.php')); exit; }
$rid = (int)$u['restaurant_id'];
$st = db()->prepare('SELECT id,name,slug,status,cuisine FROM restaurants WHERE id=? AND deleted_at IS NULL');
$st->execute([$rid]); $r = $st->fetch() ?: ['name' => 'Your restaurant', 'slug' => '', 'status' => 'unknown', 'cuisine' => null];
$one = static function (string $sql) use ($rid): int|float {
  $q = db()->prepare($sql); $q->execute([$rid]); return $q->fetchColumn() ?: 0;
};
$todayOrders = (int)$one('SELECT COUNT(*) FROM orders WHERE restaurant_id=? AND created_at >= CURDATE() AND status <> \'cancelled\'');
$todaySales = (float)$one("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE restaurant_id=? AND created_at >= CURDATE() AND payment_status='paid' AND status <> 'cancelled'");
$pending = (int)$one("SELECT COUNT(*) FROM orders WHERE restaurant_id=? AND status IN ('new','confirmed')");
$reservations = (int)$one("SELECT COUNT(*) FROM reservations WHERE restaurant_id=? AND reservation_date=CURDATE() AND status IN ('pending','confirmed')");
$menuItems = (int)$one('SELECT COUNT(*) FROM products WHERE restaurant_id=? AND deleted_at IS NULL AND is_active=1');
$qrScans = (int)$one('SELECT COUNT(*) FROM qr_scans WHERE restaurant_id=? AND scanned_at >= CURDATE()');
$days = [];
for ($i = 6; $i >= 0; $i--) { $date = date('Y-m-d', strtotime("-$i days")); $days[$date] = ['label' => date('D', strtotime($date)), 'amount' => 0.0]; }
$from = array_key_first($days);
$sales = db()->prepare("SELECT DATE(created_at) AS day, SUM(total_amount) AS amount FROM orders WHERE restaurant_id=? AND created_at >= ? AND payment_status='paid' AND status <> 'cancelled' GROUP BY DATE(created_at)");
$sales->execute([$rid, $from]);
foreach ($sales->fetchAll() as $row) if (isset($days[$row['day']])) $days[$row['day']]['amount'] = (float)$row['amount'];
$maxSale = max(1, ...array_column($days, 'amount'));
$fmtMoney = static fn(float|int $amount): string => 'KSh ' . number_format((float)$amount, 0);
$recent = db()->prepare('SELECT order_number,status,total_amount,order_type,customer_name,created_at FROM orders WHERE restaurant_id=? ORDER BY created_at DESC LIMIT 5');
$recent->execute([$rid]); $recentOrders = $recent->fetchAll();
$lowCount=0;$lowIngredients=[];
if(has_permission($u,'manage_inventory')){$lowQ=db()->prepare('SELECT COUNT(*) FROM ingredients WHERE restaurant_id=? AND current_stock<=min_stock');$lowQ->execute([$rid]);$lowCount=(int)$lowQ->fetchColumn();if($lowCount){$lowList=db()->prepare('SELECT name,current_stock,min_stock,unit FROM ingredients WHERE restaurant_id=? AND current_stock<=min_stock ORDER BY CASE WHEN min_stock>0 THEN current_stock/min_stock ELSE 0 END,name LIMIT 5');$lowList->execute([$rid]);$lowIngredients=$lowList->fetchAll();}}
$status = $r['status'];
$firstName = explode(' ', trim((string)$u['full_name']))[0] ?: 'there';
page_head('Restaurant overview'); nav_bar($u); ?>
<div class="dash-shell" id="overview">
  <main class="dash-main">
    <div class="dash-topline"><div><div class="eyebrow dark-eyebrow">RESTAURANT WORKSPACE</div><h1>Good day, <?= e($firstName) ?> <span class="wave">✳</span></h1><p class="dash-muted">Here’s what’s happening at <b><?= e($r['name']) ?></b>.</p></div>
      <div class="dash-top-actions"><span class="status-pill <?= e($status) ?>"><i></i><?= e(ucfirst($status)) ?></span><?php if ($status === 'active' && $r['slug']): ?><a class="btn sm" href="<?= e(url('restaurant/' . $r['slug'])) ?>">View storefront ↗</a><?php endif ?></div>
    </div>
    <?php if ($status === 'pending'): ?><div class="dash-notice"><b>Your application is being reviewed.</b><span>Your workspace is ready. We’ll let you know when your restaurant is approved and visible to customers.</span></div><?php endif ?>
    <?php if ($lowCount): ?><div class="dash-notice stock-alert"><b><?= number_format($lowCount) ?> ingredient<?= $lowCount===1?'':'s' ?> at or below minimum stock</b><div class="stock-alert-items"><?php foreach($lowIngredients as $ingredient): ?><span><b><?= e($ingredient['name']) ?></b> · <?= number_format((float)$ingredient['current_stock'],3) ?> / <?= number_format((float)$ingredient['min_stock'],3) ?> <?= e($ingredient['unit']) ?></span><?php endforeach ?><?php if($lowCount>count($lowIngredients)): ?><span>and <?= number_format($lowCount-count($lowIngredients)) ?> more</span><?php endif ?></div><a href="<?= e(url('restaurant/inventory.php')) ?>">Review inventory →</a></div><?php endif ?>
    <section class="metric-grid" aria-label="Today's performance">
      <article class="metric-card sales-metric"><div class="metric-label">Today's sales <span>↗</span></div><div class="metric-value"><?= e($fmtMoney($todaySales)) ?></div><div class="metric-foot">Paid orders today</div><div class="metric-watermark">KSh</div></article>
      <article class="metric-card"><div class="metric-label">Orders today <span class="metric-icon">▤</span></div><div class="metric-value"><?= number_format($todayOrders) ?></div><div class="metric-foot">Across all order types</div></article>
      <article class="metric-card"><div class="metric-label">Needs attention <span class="metric-icon warm">●</span></div><div class="metric-value"><?= number_format($pending) ?></div><div class="metric-foot">New or awaiting confirmation</div></article>
      <article class="metric-card"><div class="metric-label">Reservations today <span class="metric-icon green">▣</span></div><div class="metric-value"><?= number_format($reservations) ?></div><div class="metric-foot">Pending or confirmed</div></article>
    </section>
    <section class="dash-columns">
      <article class="dash-panel chart-panel"><div class="panel-heading"><div><h2>Sales overview</h2><p>Paid sales · last 7 days</p></div><span class="panel-tag">THIS WEEK</span></div>
        <div class="chart-total"><?= e($fmtMoney(array_sum(array_column($days, 'amount')))) ?><small> this week</small></div>
        <div class="bar-chart" role="img" aria-label="Daily paid sales over the last seven days">
          <?php foreach ($days as $day): $height = max(4, (int)round($day['amount'] / $maxSale * 100)); ?><div class="bar-col"><div class="bar-value"><?= $day['amount'] > 0 ? e(number_format($day['amount'], 0)) : '' ?></div><div class="bar-track"><i style="height:<?= $height ?>%"></i></div><span><?= e($day['label']) ?></span></div><?php endforeach ?>
        </div>
      </article>
      <article class="dash-panel quick-panel" id="actions"><div class="panel-heading"><div><h2>Quick actions</h2><p>Keep the service moving</p></div><span class="quick-spark">✳</span></div>
        <a class="quick-action" href="<?= e(url('restaurant/orders.php')) ?>"><span class="quick-icon coral">▤</span><span><b>Open order board</b><small><?= $pending ? number_format($pending) . ' orders need attention' : 'Review incoming orders' ?></small></span><i>→</i></a>
        <a class="quick-action" href="<?= e(url('restaurant/menu.php')) ?>"><span class="quick-icon sage">▦</span><span><b>Update your menu</b><small><?= number_format($menuItems) ?> active menu items</small></span><i>→</i></a>
        <a class="quick-action" href="<?= e(url('restaurant/qr.php')) ?>"><span class="quick-icon gold">▧</span><span><b>Tables &amp; QR codes</b><small><?= number_format($qrScans) ?> scans today</small></span><i>→</i></a>
        <?php if (has_permission($u, 'view_reports')): ?><a class="quick-action" href="<?= e(url('restaurant/reports.php')) ?>"><span class="quick-icon sage">▥</span><span><b>Sales reports</b><small>Explore sales and order trends</small></span><i>→</i></a><?php endif ?>
      </article>
    </section>
    <section class="dash-panel recent-panel" id="activity"><div class="panel-heading"><div><h2>Recent orders</h2><p>Your latest customer activity</p></div><a class="panel-link" href="<?= e(url('restaurant/orders.php')) ?>">View all orders →</a></div>
      <?php if ($recentOrders): ?><div class="recent-table"><div class="recent-head"><span>ORDER</span><span>CUSTOMER</span><span>TYPE</span><span>STATUS</span><span>TOTAL</span></div>
        <?php foreach ($recentOrders as $order): ?><div class="recent-row"><b>#<?= e($order['order_number']) ?></b><span><?= e($order['customer_name'] ?: 'Guest') ?></span><span><?= e(ucwords(str_replace('_', ' ', $order['order_type']))) ?></span><span><i class="order-status <?= e($order['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $order['status']))) ?></i></span><b><?= e($fmtMoney($order['total_amount'])) ?></b></div><?php endforeach ?>
      </div><?php else: ?><div class="dash-empty"><span>✳</span><b>Your first order is just around the corner</b><p>When a customer places an order, it will show up here.</p></div><?php endif ?>
    </section>
    <footer class="dash-footer"><span><?= e($r['name']) ?> · Dineqor workspace</span><span>Menu items <b><?= number_format($menuItems) ?></b> <i></i> QR scans today <b><?= number_format($qrScans) ?></b></span></footer>
  </main>
</div>
<?php page_foot();
