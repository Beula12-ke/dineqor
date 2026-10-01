<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
page_head('Orders'); nav_bar($u); ?>
<main class="wrap">
  <div class="row-head"><div><h1>Orders</h1><p class="sub">Your latest customer activity.</p></div>
  <button class="btn sm ghost" id="refresh" type="button">Refresh</button></div>
  <section class="dash-panel recent-panel orders-activity-panel"><div class="panel-heading"><div><h2>Customer activity</h2><p>All recent orders for this restaurant</p></div><span class="panel-tag">AUTO REFRESH · 10 SEC</span></div>
    <div id="board" class="recent-table" aria-busy="true"><div class="table-loading"><span class="loading-line"></span>Loading orders…</div></div>
  </section>
</main>
<?php page_foot('orders-board.js');
