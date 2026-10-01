<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_staff', 'restaurant_owner']);
$links = [];
if (has_permission($u, 'view_orders')) $links[] = ['Orders', 'View and update incoming orders', '▤', url('restaurant/orders.php'), 'coral'];
if (in_array($u['staff_role'] ?? '', ['waiter', 'manager'], true) && has_permission($u, 'manage_tables')) $links[] = ['Waiter service', 'Take orders, serve ready food, and manage tables', '♧', url('staff/service.php'), 'sage'];
if (in_array($u['staff_role'] ?? '', ['chef', 'manager'], true) && has_permission($u, 'view_orders')) $links[] = ['Kitchen display', 'Prepare incoming orders and send them to service', '◉', url('restaurant/kitchen.php'), 'coral'];
if (has_permission($u, 'manage_pos')) $links[] = ['Point of sale', 'Start a counter or dine-in sale', '＄', url('restaurant/pos.php'), 'sage'];
if (has_permission($u, 'view_products')) $links[] = ['Menu', 'Check items and availability', '▦', url('restaurant/menu.php'), 'gold'];
if (has_permission($u, 'manage_reservations')) $links[] = ['Reservations', 'Review today’s table requests', '▣', url('restaurant/reservations.php'), 'sage'];
if (($u['staff_role'] ?? '') !== 'waiter' && has_permission($u, 'manage_tables')) $links[] = ['Tables & QR', 'Open tables and table codes', '▧', url('restaurant/qr.php'), 'coral'];
if (has_permission($u, 'manage_inventory')) $links[] = ['Inventory', 'Track ingredients and stock movements', '◩', url('restaurant/inventory.php'), 'gold'];
$lowCount=0;$lowIngredients=[];
if(has_permission($u,'manage_inventory')){$rid=(int)$u['restaurant_id'];$lowQ=db()->prepare('SELECT COUNT(*) FROM ingredients WHERE restaurant_id=? AND current_stock<=min_stock');$lowQ->execute([$rid]);$lowCount=(int)$lowQ->fetchColumn();if($lowCount){$lowList=db()->prepare('SELECT name,current_stock,min_stock,unit FROM ingredients WHERE restaurant_id=? AND current_stock<=min_stock ORDER BY CASE WHEN min_stock>0 THEN current_stock/min_stock ELSE 0 END,name LIMIT 5');$lowList->execute([$rid]);$lowIngredients=$lowList->fetchAll();}}
page_head('Staff workspace'); nav_bar($u); ?>
<main class="staff-home">
  <div class="staff-greeting"><div><div class="eyebrow dark-eyebrow">YOUR RESTAURANT WORKSPACE</div><h1>Welcome back, <?= e(explode(' ', (string)$u['full_name'])[0] ?: 'team') ?>.</h1><p>Pick up where your shift needs you.</p></div><div class="staff-role-card"><span class="staff-role-icon">✳</span><span><small>YOUR ROLE</small><b><?= e(ucwords(str_replace('_', ' ', $u['staff_role'] ?? 'team member'))) ?></b></span></div></div>
  <?php if($lowCount): ?><div class="dash-notice stock-alert"><b><?= number_format($lowCount) ?> ingredient<?= $lowCount===1?'':'s' ?> at or below minimum stock</b><div class="stock-alert-items"><?php foreach($lowIngredients as $ingredient): ?><span><b><?= e($ingredient['name']) ?></b> · <?= number_format((float)$ingredient['current_stock'],3) ?> / <?= number_format((float)$ingredient['min_stock'],3) ?> <?= e($ingredient['unit']) ?></span><?php endforeach ?><?php if($lowCount>count($lowIngredients)): ?><span>and <?= number_format($lowCount-count($lowIngredients)) ?> more</span><?php endif ?></div><a href="<?= e(url('restaurant/inventory.php')) ?>">Review inventory →</a></div><?php endif ?>
  <section class="staff-task-grid" aria-label="Available work areas">
    <?php foreach ($links as $link): ?><a class="staff-task <?= e($link[4]) ?>" href="<?= e($link[3]) ?>"><span class="staff-task-icon"><?= e($link[2]) ?></span><span class="staff-task-copy"><b><?= e($link[0]) ?></b><small><?= e($link[1]) ?></small></span><span class="staff-task-arrow">→</span></a><?php endforeach ?>
  </section>
  <?php if (!$links): ?><div class="dash-empty"><span>◉</span><b>No workspace tools are assigned yet</b><p>Ask your restaurant manager to review your account permissions.</p></div><?php endif ?>
  <section class="shift-note"><div class="shift-note-mark">✦</div><div><b>One smooth service at a time.</b><p>Your workspace only shows tools available for your assigned role.</p></div><?php if (has_permission($u, 'view_reports')): ?><a href="<?= e(url('restaurant/dashboard.php')) ?>">Restaurant overview →</a><?php endif ?></section>
</main>
<?php page_foot();
