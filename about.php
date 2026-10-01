<?php
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/public-layout.php';
page_head('About Dineqor');
public_nav('about');
?>
<main class="public-content-page">
  <section class="content-page-hero"><div class="wrap"><div class="eyebrow">GOOD FOOD. GREAT BUSINESS.</div><h1>Bringing diners and local <em>restaurants together.</em></h1><p>Dineqor helps people find restaurants and order with ease, while giving restaurant teams tools to manage everyday service.</p></div></section>
  <section class="wrap about-content content-page-body"><article class="about-story"><span class="about-symbol" aria-hidden="true">D</span><div><h2>Made for the whole dining experience</h2><p>From discovering a nearby restaurant to placing an order and following its progress, Dineqor connects customers with the restaurants they love.</p><p>Restaurant teams can manage their menu, orders, tables, staff, and service from one workspace.</p><a class="text-link" href="<?= e(url('restaurants.php')) ?>">Find restaurants <span aria-hidden="true">→</span></a></div></article>
    <section class="partner-band about-partner"><div class="partner-inner"><div><div class="eyebrow">FOR RESTAURANTS</div><h2>Put your restaurant<br>on the map.</h2><p>Bring your menu and service online with Dineqor.</p></div><a class="partner-button" href="<?= e(url('register-restaurant.php')) ?>">Become a partner <span>→</span></a></div></section>
  </section>
</main>
<?php public_footer(); page_foot(); ?>
