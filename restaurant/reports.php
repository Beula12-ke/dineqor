<?php require_once __DIR__ . '/../includes/page.php';
$u=page_guard(['restaurant_owner','restaurant_staff']);
if (!has_permission($u,'view_reports')) { http_response_code(403); page_head('Reports access'); nav_bar($u); echo '<main class="wrap"><div class="empty"><b>You do not have access to reports.</b>Ask your restaurant owner to review your role permissions.</div></main>'; page_foot(); exit; }
page_head('Restaurant reports'); nav_bar($u); ?>
<main class="wrap reports-page">
  <header class="reports-heading"><div><div class="eyebrow dark-eyebrow">RESTAURANT PERFORMANCE</div><h1>Reports</h1><p>Review paid sales, order flow and top-selling items for a date range.</p></div><button class="btn sm ghost" type="button" id="downloadReport">Download CSV</button></header>
  <form class="reports-filters" id="reportFilters"><label>From<input type="date" name="from" required></label><label>To<input type="date" name="to" required></label><button class="btn sm" type="submit">Update report</button><span class="msg" id="reportMessage" role="status"></span></form>
  <section class="reports-metrics" id="reportMetrics" aria-live="polite"><div class="report-loading">Loading report…</div></section>
  <section class="reports-main-grid">
    <article class="dash-panel reports-chart"><div class="panel-heading"><div><h2>Daily paid sales</h2><p id="reportRangeLabel">Selected date range</p></div></div><div id="dailySales" class="daily-sales-chart" aria-live="polite"></div></article>
    <article class="dash-panel reports-breakdown"><div class="panel-heading"><div><h2>Order types</h2><p>Orders and paid sales by channel.</p></div></div><div id="orderTypeBreakdown"></div></article>
    <article class="dash-panel reports-breakdown"><div class="panel-heading"><div><h2>Payment methods</h2><p>Paid orders grouped by payment method.</p></div></div><div id="paymentBreakdown"></div></article>
    <article class="dash-panel reports-breakdown"><div class="panel-heading"><div><h2>Order status</h2><p>All orders created in this period.</p></div></div><div id="statusBreakdown"></div></article>
  </section>
  <article class="dash-panel top-products-panel"><div class="panel-heading"><div><h2>Top-selling items</h2><p>Ranked by quantity on paid orders.</p></div></div><div id="topProducts"></div></article>
</main>
<?php page_foot('reports.js');
