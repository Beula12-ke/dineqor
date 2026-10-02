<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
$isWaiter = ($u['staff_role'] ?? '') === 'waiter';
$isDelivery = ($u['staff_role'] ?? '') === 'delivery';
page_head('Orders'); nav_bar($u);
if ($isWaiter): ?>
<main class="wrap wo-page">
  <div class="row-head"><div><h1>Orders</h1><p class="sub">Follow each table's order from the kitchen to the table.</p></div>
  <button class="btn sm ghost" id="woRefresh" type="button">Refresh</button></div>
  <section class="wo-stats" id="woStats" aria-live="polite"></section>
  <div class="wo-tabs" id="woTabs" role="tablist"></div>
  <section class="wo-grid" id="woGrid" aria-live="polite" aria-busy="true"><div class="table-loading"><span class="loading-line"></span>Loading orders…</div></section>
</main>
<?php page_foot('waiter-orders.js'); exit; endif; ?>
<?php if ($isDelivery): ?>
<main class="wrap wo-page">
  <div class="row-head"><div><h1>Deliveries</h1><p class="sub">Collect ready orders from the kitchen and take them to the customer.</p></div>
  <button class="btn sm ghost" id="woRefresh" type="button">Refresh</button></div>
  <section class="wo-stats" id="woStats" aria-live="polite"></section>
  <div class="wo-tabs" id="woTabs" role="tablist"></div>
  <section class="wo-grid" id="woGrid" aria-live="polite" aria-busy="true"><div class="table-loading"><span class="loading-line"></span>Loading deliveries…</div></section>
</main>
<?php page_foot('delivery-orders.js'); exit; endif; ?>
<main class="wrap">
  <div class="row-head"><div><h1>Orders</h1><p class="sub">Your latest customer activity.</p></div>
  <button class="btn sm ghost" id="refresh" type="button">Refresh</button></div>
  <section class="dash-panel recent-panel orders-activity-panel"><div class="panel-heading"><div><h2>Customer activity</h2><p>All recent orders for this restaurant</p></div><span class="panel-tag">AUTO REFRESH · 10 SEC</span></div>
    <div id="board" class="recent-table" aria-busy="true"><div class="table-loading"><span class="loading-line"></span>Loading orders…</div></div>
  </section>
</main>
<?php page_foot('orders-board.js');
