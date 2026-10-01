<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['platform_admin']);
page_head('Platform overview'); nav_bar($u); ?>
<div class="dash-shell admin-shell" id="overview">
  <main class="dash-main">
    <div class="dash-topline"><div><div class="eyebrow dark-eyebrow">PLATFORM CONTROL CENTER</div><h1>Platform overview</h1><p class="dash-muted">Monitor restaurant activity and review new partner applications.</p></div>
      <div class="dash-top-actions"><span class="live-indicator"><i></i> Live overview</span><button class="btn sm ghost" id="refresh" type="button"><span class="refresh-mark">↻</span> Refresh</button></div>
    </div>
    <section class="metric-grid admin-metrics" id="stats" aria-label="Platform statistics"><div class="metric-card loading-card" aria-busy="true"><span class="loading-line"></span><span class="loading-line short"></span></div><div class="metric-card loading-card"></div><div class="metric-card loading-card"></div></section>
    <section class="dash-panel admin-restaurants" id="restaurants"><div class="panel-heading"><div><h2>Restaurant applications</h2><p>Review and manage the latest partners on Dineqor</p></div><span class="panel-tag">AUTO REFRESH · 30 SEC</span></div>
      <div class="table-wrap"><table class="table admin-table"><thead><tr><th>RESTAURANT</th><th>OWNER</th><th>APPLIED</th><th>STATUS</th><th><span class="sr">Actions</span></th></tr></thead>
        <tbody id="rows"><tr><td colspan="5"><div class="table-loading"><span class="loading-line"></span>Loading platform data…</div></td></tr></tbody></table></div>
      <div class="admin-table-foot"><span>Showing the latest 25 restaurants</span><span id="lastUpdated">Connecting…</span></div>
    </section>
    <footer class="dash-footer"><span>Dineqor platform administration</span><span>Updates automatically every 30 seconds</span></footer>
  </main>
</div>
<?php page_foot('admin.js');
