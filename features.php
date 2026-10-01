<?php
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/public-layout.php';
page_head('Features');
public_nav('features');
?>
<main class="public-content-page">
  <section class="content-page-hero"><div class="wrap"><div class="eyebrow">A BETTER WAY TO DINE</div><h1>Everything for a smoother <em>meal.</em></h1><p>Discover a restaurant, order at your pace, and follow your order from kitchen to table.</p><a class="partner-button" href="<?= e(url('restaurants.php')) ?>">Explore restaurants <span>→</span></a></div></section>
  <section class="wrap content-page-body"><div class="steps-grid">
    <article class="step-card"><span class="step-no">01</span><span class="step-icon">⌕</span><h3>Discover local restaurants</h3><p>Search by restaurant, cuisine, or city, then check what’s open and available.</p></article>
    <article class="step-card"><span class="step-no">02</span><span class="step-icon">▤</span><h3>Order your way</h3><p>Browse menus and choose delivery, pickup, or a QR table order where offered.</p></article>
    <article class="step-card"><span class="step-no">03</span><span class="step-icon">↗</span><h3>Follow your order</h3><p>Customers can check order progress from their Dineqor account.</p></article>
  </div></section>
</main>
<?php public_footer(); page_foot(); ?>
