<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['customer']);
page_head('Order updates'); nav_bar($u); ?>
<main class="customer-home customer-workspace customer-list-page notifications-page">
  <header class="customer-heading"><div><div class="eyebrow">YOUR ACCOUNT</div><h1>Order updates</h1><p>See when restaurants confirm, prepare, and complete your orders.</p></div><a class="customer-browse" href="<?= e(url('customer/orders.php')) ?>">My orders <span>→</span></a></header>
  <section class="dash-panel customer-panel notifications-panel">
    <div class="panel-heading"><div><h2>Notifications</h2><p>Updates are saved to your account.</p></div><button class="notification-read-all" type="button" data-read-all hidden>Mark all as read</button></div>
    <div class="notification-list" data-notification-list aria-live="polite"><div class="notification-loading">Loading your order updates…</div></div>
    <div class="msg notification-error" data-notification-error role="status"></div>
  </section>
</main>
<script>window.DINEQOR_CSRF=<?= json_encode(csrf_token()) ?>;</script>
<?php page_foot('customer-notifications.js');
