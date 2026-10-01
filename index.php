<?php
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/public-layout.php';
page_head('Order from local restaurants');
public_nav('home');
?>
<main>
  <section class="home-hero" id="top">
    <div class="hero-in home-hero-in">
      <div class="hero-copy">
        <div class="hero-brand-lockup"><svg viewBox="0 0 64 64" aria-hidden="true"><path d="M13 6v20m-7-20v11m14-11v11M6 17h14M13 26v32m26-52c10 0 17 9 17 20v26H39V26c0-11 0-20 0-20Z" fill="none" stroke="currentColor" stroke-width="4.2" stroke-linecap="round" stroke-linejoin="round"/></svg><span><b><?= e(platform_setting('platform_name', APP_NAME)) ?></b><small>Good Food. Great Business.</small></span></div>
        <h1>Discover Amazing Restaurants</h1>
        <p>Dine in <i>·</i> Order online <i>·</i> Scan a QR code <i>·</i> Great food &amp; always nearby.</p>
        <form class="search home-search" id="search" role="search" data-destination="<?= e(url('restaurants.php')) ?>">
          <label class="sr" for="q">Search for a restaurant</label>
          <span class="search-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg></span>
          <input id="q" type="search" placeholder="Search for a restaurant..." autocomplete="off">
          <button class="btn" type="submit">Find Restaurants</button>
        </form>
      </div>
    </div>
  </section>

  <section class="discovery wrap" id="restaurants">
    <div class="section-heading">
      <div><div class="eyebrow dark-eyebrow">A GOOD PLACE TO START</div><h2>Find your kind of <em>good.</em></h2><p>From quick lunches to long dinners, there’s a local spot for every craving.</p></div>
      <label class="switch"><input type="checkbox" id="open"> <span>Open now</span></label>
    </div>
    <div class="chips" id="chips" aria-label="Filter by cuisine"></div>
    <div class="results-caption"><h3 id="count" aria-live="polite">Restaurants</h3><span>Fresh picks from independent kitchens</span></div>
    <noscript><div class="empty">Please enable JavaScript to browse restaurants.</div></noscript>
    <div class="grid" id="grid" aria-busy="true"></div>
  </section>

  <section class="how-section" id="how-it-works"><div class="wrap">
    <div class="eyebrow dark-eyebrow">GOOD FOOD, MADE SIMPLE</div><h2>From craving to <em>covered.</em></h2>
    <div class="steps-grid">
      <article class="step-card"><span class="step-no">01</span><span class="step-icon">⌕</span><h3>Find your spot</h3><p>Explore restaurants around you and see what’s cooking.</p></article>
      <article class="step-card"><span class="step-no">02</span><span class="step-icon">✳</span><h3>Pick your plate</h3><p>Browse the menu and make it yours, right from your phone.</p></article>
      <article class="step-card"><span class="step-no">03</span><span class="step-icon">↗</span><h3>Enjoy the good</h3><p>Get it delivered, collect it, or settle in for a meal out.</p></article>
    </div>
  </div></section>

  <section class="partner-band" id="about"><div class="wrap partner-inner"><div><div class="eyebrow">FOR THE PEOPLE BEHIND THE FOOD</div><h2>Your kitchen deserves<br>a bigger table.</h2><p>Bring your restaurant online and meet more of the people who’ll love it.</p></div><a class="partner-button" href="<?= e(url('register-restaurant.php')) ?>">Become a restaurant partner <span>→</span></a></div></section>
</main>
<?php public_footer(); ?>
<?php page_foot('home.js');
