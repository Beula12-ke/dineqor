<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
$canManageTables = in_array($u['staff_role'] ?? '', ['owner', 'manager'], true);
page_head('Tables & QR codes'); nav_bar($u); ?>
<main class="wrap">
  <div class="row-head"><div><h1>Tables<?= $canManageTables ? ' &amp; QR codes' : '' ?></h1><p class="sub"><?= $canManageTables ? 'Manage your dining room and table ordering links.' : 'Check table availability and update its service status.' ?></p></div>
  <?php if ($canManageTables): ?><div class="act"><button class="btn sm ghost" id="dlAll" type="button">Download all</button><button class="btn sm ghost" id="printAll" type="button">Print all</button></div><?php endif; ?></div>
  <?php if ($canManageTables): ?><div class="stats" id="stats"></div><div class="panel" id="rqr"></div><?php endif; ?>
  <h2>Tables</h2>
  <?php if ($canManageTables): ?><div class="panel"><form id="gen" class="act" novalidate><label class="sr" for="count">Number of tables</label>
    <input id="count" type="number" min="1" max="200" placeholder="Number of tables e.g. 30" style="max-width:220px">
    <button class="btn sm" type="submit">Generate tables</button><button class="btn sm ghost" id="addOne" type="button">+ Add table</button></form>
    <p class="sub" style="margin:8px 0 0">Generated tables continue after your highest existing number. Each table gets its own QR code that opens the menu and lets guests order directly from that table.</p></div><?php endif; ?>
  <div class="grid" id="tables" aria-busy="true"></div>
</main>
<div id="printArea" class="print-only"></div>
<script>window.TABLES_CAN_MANAGE=<?= $canManageTables ? 'true' : 'false' ?>;</script>
<?php page_foot('qr.js', 'qr-page.js');
