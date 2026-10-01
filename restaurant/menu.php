<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
page_head('Menu manager'); nav_bar($u); ?>
<main class="wrap">
  <div class="row-head"><div><h1>Menu</h1><p class="sub">Manage categories and menu items.</p></div>
  <div class="act"><button class="btn sm ghost" id="addCat" type="button">+ Category</button><button class="btn sm" id="addItem" type="button">+ Menu item</button></div></div>
  <div id="menu" aria-busy="true"></div>
</main>
<dialog id="dlg" class="card" style="max-width:460px"><form id="pf" novalidate>
  <h2 id="dtitle">Menu item</h2>
  <label for="name">Name</label><input id="name" name="name" required><div class="err" data-err="name"></div>
  <label for="category_id">Category</label><select id="category_id" name="category_id"></select>
  <label for="price">Price (KSh)</label><input id="price" name="price" type="number" min="0" step="1" required><div class="err" data-err="price"></div>
  <label for="description">Description</label><textarea id="description" name="description" rows="2"></textarea>
  <label for="preparation_time">Prep time (minutes)</label><input id="preparation_time" name="preparation_time" type="number" min="0"><div class="err" data-err="preparation_time"></div>
  <label for="image">Photo (JPG/PNG/WebP, max 3 MB)</label><input id="image" type="file" accept="image/jpeg,image/png,image/webp">
  <label><input type="checkbox" name="is_available" checked style="width:auto"> Available</label>
  <label><input type="checkbox" name="is_featured" style="width:auto"> Mark as popular</label>
  <div class="msg" id="pmsg"></div>
  <div class="act" style="margin-top:16px"><button class="btn sm" type="submit">Save</button><button class="btn sm ghost" type="button" id="cancel">Cancel</button></div>
</form></dialog>
<?php page_foot('menu.js');
