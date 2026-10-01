<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
page_head('Reservations'); nav_bar($u); ?>
<main class="wrap">
  <div class="row-head"><div><h1>Reservations</h1><p class="sub">Manage upcoming guest bookings.</p></div>
  <button class="btn sm ghost" id="refresh" type="button">Refresh</button></div>
  <div id="list" aria-busy="true"></div>
</main>
<?php page_foot('reservations-board.js');
