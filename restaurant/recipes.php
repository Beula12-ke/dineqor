<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
page_head('Recipes'); nav_bar($u); ?>
<main class="wrap">
  <div class="row-head"><div><h1>Recipes</h1><p class="sub"><a href="<?= e(url('restaurant/inventory.php')) ?>">&larr; Inventory</a></p></div></div>
  <p class="sub">Link ingredients to a menu item so stock is deducted automatically every time it sells. Items with no recipe are left untracked - that's fine for drinks or anything you don't want to track.</p>
  <div class="panel">
    <label for="product">Menu item</label>
    <select id="product"><option value="">Choose a menu item&hellip;</option></select>
  </div>
  <div class="panel" id="editor" style="margin-top:16px;display:none">
    <div class="row-head"><h2 id="pname"></h2><button class="btn sm ghost" type="button" id="addLine">+ Ingredient</button></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Ingredient</th><th>Quantity per order</th><th></th></tr></thead>
      <tbody id="lines"></tbody></table></div>
    <div class="msg" id="rmsg"></div>
    <div class="act" style="margin-top:16px"><button class="btn sm" id="save" type="button">Save recipe</button></div>
  </div>
</main>
<?php page_foot('recipes.js');
