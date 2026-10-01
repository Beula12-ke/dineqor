<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
if (!in_array($u['staff_role'] ?? '', ['chef', 'manager', 'owner'], true) || !has_permission($u, 'view_orders')) {
    http_response_code(403); page_head('Kitchen access'); nav_bar($u);
    echo '<main class="wrap"><div class="empty"><b>You do not have access to the kitchen display.</b>Ask your restaurant owner to assign you a kitchen role.</div></main>';
    page_foot(); exit;
}
page_head('Kitchen display'); nav_bar($u); ?>
<main class="wrap kitchen-page">
  <header class="kitchen-heading">
    <div><div class="eyebrow dark-eyebrow">RESTAURANT OPERATIONS</div><h1>Kitchen display</h1><p>Prepare incoming orders and send them to the service team when they’re ready.</p></div>
    <div class="kitchen-tools"><span class="kitchen-live"><i></i> Live updates</span><button class="btn sm ghost" id="kitchenFullscreen" type="button">Full screen</button><button class="btn sm ghost" id="kitchenRefresh" type="button">Refresh</button></div>
  </header>
  <section class="kitchen-summary" id="kitchenSummary" aria-live="polite"><span>Loading orders…</span></section>
  <div class="kitchen-board" id="kitchenBoard" aria-busy="true"></div>
</main>
<?php page_foot('kitchen.js');
