<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['customer']);
$q = db()->prepare('SELECT id,name,reservation_date,reservation_time,guests,status,created_at FROM reservations WHERE customer_user_id=? ORDER BY reservation_date DESC,reservation_time DESC LIMIT 100');
$q->execute([(int)$u['id']]); $reservations = $q->fetchAll();
page_head('My reservations'); nav_bar($u); ?>
<main class="customer-home customer-workspace customer-list-page">
  <header class="customer-heading"><div><div class="eyebrow">YOUR ACCOUNT</div><h1>Reservations</h1><p>All your upcoming and past table bookings.</p></div><a class="customer-browse" href="<?= e(url()) ?>">Find a table <span>→</span></a></header>
  <section class="dash-panel customer-panel"><div class="panel-heading"><div><h2>Reservation history</h2><p><?= number_format(count($reservations)) ?> most recent bookings</p></div></div>
    <?php if ($reservations): ?><div class="customer-order-list"><?php foreach ($reservations as $reservation): $past = strtotime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']) < time(); $canCancel = !$past && in_array($reservation['status'], ['pending','confirmed'], true); ?><article class="customer-order reservation-row" data-reservation="<?= (int)$reservation['id'] ?>"><span class="customer-order-mark reservation-mark">▣</span><span class="customer-order-main"><b><?= e($reservation['name']) ?> · <?= number_format((int)$reservation['guests']) ?> guests</b><small><?= e(date('D, M j, Y', strtotime($reservation['reservation_date']))) ?> at <?= e(date('g:i A', strtotime($reservation['reservation_time']))) ?></small></span><span class="order-status <?= e($past && in_array($reservation['status'], ['pending','confirmed'], true) ? 'completed' : $reservation['status']) ?>" data-reservation-status><?= e($past && in_array($reservation['status'], ['pending','confirmed'], true) ? 'Past' : ucfirst(str_replace('_', ' ', $reservation['status']))) ?></span><?php if ($canCancel): ?><button class="reservation-cancel" type="button" data-cancel-reservation="<?= (int)$reservation['id'] ?>">Cancel</button><?php endif ?></article><?php endforeach ?></div>
    <?php else: ?><div class="customer-section-empty"><span>▣</span><div><b>No reservations yet</b><p>Your restaurant table bookings will be listed here.</p></div><a href="<?= e(url()) ?>">Explore restaurants →</a></div><?php endif ?>
  </section>
</main>
<?php page_foot('customer.js');
