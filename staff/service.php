<?php require_once __DIR__ . '/../includes/page.php';
$u=page_guard(['restaurant_owner','restaurant_staff']);
if (!in_array($u['staff_role'] ?? '', ['waiter','manager','owner'], true) || !has_permission($u,'view_orders') || !has_permission($u,'manage_tables')) {
    http_response_code(403); page_head('Service access'); nav_bar($u);
    echo '<main class="wrap"><div class="empty"><b>You do not have access to waiter service.</b>Ask your restaurant owner to assign the waiter role.</div></main>';
    page_foot(); exit;
}
page_head('Waiter service'); nav_bar($u); ?>
<main class="wrap service-page">
  <header class="service-heading"><div><div class="eyebrow dark-eyebrow">RESTAURANT OPERATIONS</div><h1>Waiter service</h1><p>Take table orders, follow kitchen handoffs, and keep tables moving through service.</p></div><button class="btn sm ghost" id="serviceRefresh" type="button">Refresh</button></header>
  <section class="service-summary" id="serviceSummary" aria-live="polite">Loading service…</section>
  <section class="service-section"><div class="service-section-title"><div><h2>Ready to serve</h2><p>Food marked ready by the kitchen.</p></div><span id="readyCount" class="service-count">0</span></div><div class="ready-list" id="readyList" aria-live="polite"><div class="inventory-loading">Loading ready orders…</div></div></section>
  <section class="service-section"><div class="service-section-title"><div><h2>Tables</h2><p>Open orders can be transferred; cleared tables can be returned to service.</p></div></div><div class="service-tables" id="serviceTables" aria-live="polite"></div></section>
  <section class="service-section service-order-section"><div class="service-section-title"><div><h2>Send an order to the kitchen</h2><p>Orders are saved as unpaid dine-in orders and appear on the kitchen display.</p></div></div>
    <div class="service-order-layout">
      <div class="service-menu-side">
        <div class="service-order-fields"><label>Table<select id="serviceTable" required></select></label><label>Guest name <span>Optional</span><input id="serviceGuest" maxlength="150"></label><label>Phone <span>Optional</span><input id="servicePhone" type="tel" maxlength="30"></label></div>
        <div class="service-menu-tools"><input id="serviceSearch" type="search" placeholder="Search menu"><div class="service-categories" id="serviceCategories"></div></div>
        <div class="service-products" id="serviceProducts" aria-busy="true"></div>
      </div>
      <aside class="service-cart"><h3>Current order</h3><div class="service-cart-lines" id="serviceCartLines"><div class="inventory-empty compact"><b>No items yet</b><p>Add menu items to get started.</p></div></div><label class="service-instructions">Note for the kitchen<textarea id="serviceInstructions" maxlength="1000" rows="2" placeholder="Optional preparation note"></textarea></label><div class="service-cart-total"><span>Order total</span><b id="serviceTotal">KSh 0</b></div><div class="msg" id="serviceMessage" role="status"></div><button class="btn" id="serviceSend" type="button">Send order to kitchen</button></aside>
    </div>
  </section>
</main>
<?php page_foot('service.js');
