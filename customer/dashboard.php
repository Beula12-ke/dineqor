<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['customer']); $userId = (int)$u['id'];
$q = db()->prepare('SELECT COUNT(*) FROM orders WHERE customer_user_id=?'); $q->execute([$userId]); $orderCount = (int)$q->fetchColumn();
$q = db()->prepare("SELECT COUNT(*) FROM reservations WHERE customer_user_id=? AND status IN ('pending','confirmed') AND reservation_date >= CURDATE()"); $q->execute([$userId]); $reservationCount = (int)$q->fetchColumn();
$q = db()->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=? AND restaurant_id IS NOT NULL AND product_id IS NULL"); $q->execute([$userId]); $favoriteCount = (int)$q->fetchColumn();
$q = db()->prepare("SELECT COUNT(*) FROM orders o WHERE o.customer_user_id=? AND o.status IN ('completed','delivered') AND NOT EXISTS (SELECT 1 FROM reviews rv WHERE rv.order_id=o.id AND rv.customer_user_id=o.customer_user_id)"); $q->execute([$userId]); $reviewCount = (int)$q->fetchColumn();
$rewardQ=db()->prepare("SELECT r.name,COALESCE(la.points_balance,0) AS points_balance FROM loyalty_settings ls JOIN restaurants r ON r.id=ls.restaurant_id LEFT JOIN loyalty_accounts la ON la.restaurant_id=r.id AND la.user_id=? WHERE ls.is_enabled=1 AND r.status='active' AND r.deleted_at IS NULL ORDER BY la.points_balance DESC,r.name LIMIT 4");$rewardQ->execute([$userId]);$rewardPrograms=$rewardQ->fetchAll();
$firstName = explode(' ', trim((string)$u['full_name']))[0] ?: 'there';
page_head('Your Dineqor'); nav_bar($u); ?>
<main class="customer-home customer-workspace">
  <header class="customer-heading"><div><div class="eyebrow">CUSTOMER OVERVIEW</div><h1>Welcome back, <?= e($firstName) ?> <span>✳</span></h1><p>Your Dineqor account, all in one place.</p></div><a class="customer-browse" href="<?= e(url()) ?>">Browse restaurants <span>→</span></a></header>
  <section class="customer-metrics" aria-label="Account summary">
    <a href="<?= e(url('customer/orders.php')) ?>"><span class="customer-metric-icon">▤</span><span><small>ORDERS PLACED</small><b><?= number_format($orderCount) ?></b></span><span class="metric-chevron">→</span></a>
    <a href="<?= e(url('customer/reservations.php')) ?>"><span class="customer-metric-icon sage">▣</span><span><small>UPCOMING RESERVATIONS</small><b><?= number_format($reservationCount) ?></b></span><span class="metric-chevron">→</span></a>
    <a href="<?= e(url('customer/saved.php')) ?>"><span class="customer-metric-icon rose">♡</span><span><small>SAVED PLACES</small><b><?= number_format($favoriteCount) ?></b></span><span class="metric-chevron">→</span></a>
    <a href="<?= e(url('customer/reviews.php')) ?>"><span class="customer-metric-icon review-icon">★</span><span><small>ORDERS TO REVIEW</small><b><?= number_format($reviewCount) ?></b></span><span class="metric-chevron">→</span></a>
  </section>
  <?php if($rewardPrograms): ?><section class="dash-panel customer-panel dashboard-rewards"><div class="panel-heading"><div><h2>Your reward points</h2><p>Balances stay separate for each restaurant.</p></div><a class="panel-link" href="<?= e(url('customer/rewards.php')) ?>">All rewards →</a></div><div class="dashboard-reward-list"><?php foreach($rewardPrograms as $program): ?><a href="<?= e(url('customer/rewards.php')) ?>"><span class="loyalty-points-mark">★</span><span><b><?= e($program['name']) ?></b><small>Restaurant balance</small></span><strong><?= number_format((int)$program['points_balance']) ?> pts</strong></a><?php endforeach ?></div></section><?php endif ?>
  <section class="customer-shortcuts"><div class="panel-heading"><div><h2>Your account</h2><p>Choose a section to continue</p></div></div><div class="customer-shortcut-grid">
    <a class="customer-shortcut" href="<?= e(url('customer/orders.php')) ?>"><span class="customer-shortcut-icon">▤</span><span><b>My orders</b><small>View order history and status</small></span><i>→</i></a>
    <a class="customer-shortcut sage" href="<?= e(url('customer/reservations.php')) ?>"><span class="customer-shortcut-icon">▣</span><span><b>Reservations</b><small>See your upcoming table bookings</small></span><i>→</i></a>
    <a class="customer-shortcut rose" href="<?= e(url('customer/saved.php')) ?>"><span class="customer-shortcut-icon">♡</span><span><b>Saved places</b><small>Return to restaurants you love</small></span><i>→</i></a>
  </div></section>
</main>
<?php page_foot();
