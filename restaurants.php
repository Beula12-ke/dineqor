<?php
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/public-layout.php';
page_head('Find restaurants');
public_nav('restaurants');
?>
<main class="public-directory">
  <section class="directory-hero"><div class="wrap"><div class="eyebrow">A GOOD PLACE TO START</div><h1>Find your next <em>favourite.</em></h1><p>Search local restaurants, explore menus, and find somewhere delicious nearby.</p>
    <form class="search directory-search" id="search" role="search">
      <label class="sr" for="q">Search restaurants, cuisine or city</label><span class="search-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg></span>
      <input id="q" type="search" placeholder="Restaurant, cuisine or city" autocomplete="off"><button class="btn" type="submit">Search restaurants</button>
    </form>
  </div></section>
  <section class="discovery wrap directory-results" id="restaurants">
    <div class="section-heading"><div><div class="eyebrow dark-eyebrow">LOCAL FAVOURITES</div><h2>Restaurants near you</h2><p>Choose a place and see what’s on the menu.</p></div><label class="switch"><input type="checkbox" id="open"> <span>Open now</span></label></div>
    <div class="chips" id="chips" aria-label="Filter by cuisine"></div>
    <div class="results-caption"><h3 id="count" aria-live="polite">Restaurants</h3><span>Browse independent kitchens</span></div>
    <noscript><div class="empty">Please enable JavaScript to browse restaurants.</div></noscript>
    <div class="grid" id="grid" aria-busy="true"></div>
  </section>
</main>
<?php public_footer(); page_foot('home.js'); ?>
