<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['customer']);
$q = db()->prepare("SELECT r.id,r.slug,r.name,r.cuisine,r.city,r.cover_path,r.primary_color FROM favorites f JOIN restaurants r ON r.id=f.restaurant_id WHERE f.user_id=? AND f.product_id IS NULL AND r.status='active' AND r.deleted_at IS NULL ORDER BY f.created_at DESC LIMIT 100");
$q->execute([(int)$u['id']]); $favorites = $q->fetchAll();
page_head('Saved places'); nav_bar($u); ?>
<main class="customer-home customer-workspace customer-list-page">
  <header class="customer-heading"><div><div class="eyebrow">YOUR ACCOUNT</div><h1>Saved places</h1><p>Your shortlist of restaurants to visit again.</p></div><a class="customer-browse" href="<?= e(url()) ?>">Explore restaurants <span>→</span></a></header>
  <section class="saved-restaurants customer-panel"><div class="panel-heading"><div><h2>Your favourites</h2><p><span id="favoriteCount"><?= number_format(count($favorites)) ?></span> saved restaurants</p></div></div>
    <?php if ($favorites): ?><div class="saved-grid" id="savedRestaurants"><?php foreach ($favorites as $favorite): $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$favorite['primary_color']) ? $favorite['primary_color'] : '#e6a27f'; $cover = public_url($favorite['cover_path']); ?>
      <article class="saved-card" data-saved-card="<?= (int)$favorite['id'] ?>"><a class="saved-card-link" href="<?= e(url('restaurant/' . rawurlencode($favorite['slug']))) ?>"><span class="saved-card-image" style="<?= $cover ? 'background-image:url(\'' . e($cover) . '\')' : 'background-color:' . e($color) ?>"><?= $cover ? '' : '✳' ?></span><span class="saved-card-copy"><b><?= e($favorite['name']) ?></b><small><?= e(implode(' · ', array_filter([$favorite['cuisine'], $favorite['city']]))) ?></small></span></a><button type="button" class="favorite-toggle saved-remove" data-favorite-toggle data-restaurant-id="<?= (int)$favorite['id'] ?>" data-favorite="true" aria-pressed="true" aria-label="Remove <?= e($favorite['name']) ?> from saved places">♥</button></article>
    <?php endforeach ?></div><?php else: ?><div class="saved-empty" id="savedRestaurants"><span>♡</span><b>No saved places yet</b><p>Tap the heart on a restaurant page to keep it here.</p><a href="<?= e(url()) ?>">Find a restaurant →</a></div><?php endif ?>
  </section>
</main>
<?php page_foot('customer.js');
