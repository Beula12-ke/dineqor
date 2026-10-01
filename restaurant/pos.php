<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
page_head('POS'); nav_bar($u); ?>
<main class="pos-wrap">
  <section class="pos-catalog">
    <div class="row-head"><h1>POS</h1></div>
    <input id="posSearch" type="search" placeholder="Search products" style="margin-bottom:10px">
    <div class="cats" id="posCats"></div>
    <div class="grid" id="posGrid" aria-busy="true"></div>
  </section>
  <aside class="pos-cart">
    <h2>Current sale</h2>
    <div class="act" style="margin-bottom:10px">
      <select id="posType"><option value="pos">Takeaway (POS)</option><option value="dine_in">Dine-in</option><option value="takeaway">Takeaway (kitchen)</option></select>
      <select id="posTable" hidden></select>
    </div>
    <div id="posLines"><div class="empty" style="border:0">No items yet.</div></div>
    <div class="ctotal"><span>Subtotal</span><span id="posSub">KSh 0</span></div>
    <label for="posDiscount">Discount (KSh)</label><input id="posDiscount" type="number" min="0" step="1" value="0">
    <div class="ctotal"><span>Total</span><span id="posTotal">KSh 0</span></div>
    <label for="posMethod">Payment method</label>
    <select id="posMethod"><option value="cash">Cash</option><option value="card">Card</option><option value="mpesa">M-Pesa</option><option value="bank">Bank</option><option value="other">Other</option></select>
    <label for="posName">Customer name (optional)</label><input id="posName">
    <div class="msg" id="posMsg"></div>
    <button class="btn" id="posComplete" type="button">Complete sale</button>
    <button class="btn sm ghost" id="posClear" type="button" style="margin-top:8px">Clear</button>
  </aside>
</main>
<div id="posReceipt" class="print-only"></div>
<?php page_foot('pos.js');
