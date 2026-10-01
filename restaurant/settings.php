<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
if (!has_permission($u, 'manage_settings')) { http_response_code(403); page_head('Settings access'); nav_bar($u); echo '<main class="wrap"><div class="empty"><b>You do not have access to restaurant settings.</b>Ask an owner or manager for help.</div></main>'; page_foot(); exit; }
page_head('Restaurant settings'); nav_bar($u); ?>
<main class="wrap settings-page">
  <div class="row-head settings-heading"><div><div class="eyebrow dark-eyebrow">YOUR RESTAURANT</div><h1>Settings</h1><p class="sub">Manage how guests find and order from your restaurant.</p></div><button class="btn sm" type="submit" form="settingsForm">Save changes</button></div>
  <form id="settingsForm" novalidate>
    <section class="setting-card">
      <div class="setting-title"><span class="setting-icon coral">⌂</span><div><h2>Restaurant profile</h2><p>These details appear on your public restaurant page.</p></div></div>
      <div class="setting-grid">
        <div class="setting-field"><label for="name">Restaurant name</label><input id="name" name="name" maxlength="150" required><div class="err" data-err="name"></div></div>
        <div class="setting-field"><label for="cuisine">Cuisine</label><input id="cuisine" name="cuisine" maxlength="80" placeholder="e.g. Kenyan, Italian, Cafe"><div class="err" data-err="cuisine"></div></div>
        <div class="setting-field"><label for="phone">Phone</label><input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel"><div class="err" data-err="phone"></div></div>
        <div class="setting-field"><label for="email">Public email</label><input id="email" name="email" type="email" maxlength="150" autocomplete="email"><div class="err" data-err="email"></div></div>
        <div class="setting-field setting-wide"><label for="website_url">Company website <span class="optional-label">Optional</span></label><input id="website_url" name="website_url" type="url" maxlength="2048" inputmode="url" autocomplete="url" placeholder="https://restaurant.com"><div class="field-hint">Shown as a separate “Visit website” link on your public restaurant page.</div><div class="err" data-err="website_url"></div></div>
        <div class="setting-field"><label for="city">City</label><input id="city" name="city" maxlength="80" autocomplete="address-level2"><div class="err" data-err="city"></div></div>
        <div class="setting-field"><label for="country">Country</label><input id="country" name="country" maxlength="80" autocomplete="country-name"><div class="err" data-err="country"></div></div>
        <div class="setting-field setting-wide"><label for="address">Street address</label><input id="address" name="address" maxlength="500" autocomplete="street-address"><div class="err" data-err="address"></div></div>
        <div class="setting-field setting-wide"><label for="description">About your restaurant</label><textarea id="description" name="description" rows="3" maxlength="2000" placeholder="Tell guests what makes your restaurant special."></textarea><div class="field-hint">Shown on your restaurant page · up to 2,000 characters</div></div>
        <div class="setting-field"><label for="price_range">Price range</label><select id="price_range" name="price_range"><option value="">Not set</option><option value="1">$ · Budget friendly</option><option value="2">$$ · Moderate</option><option value="3">$$$ · Premium</option><option value="4">$$$$ · Fine dining</option></select></div>
        <div class="setting-field"><label>Brand colors</label><div class="color-pair"><label class="color-input" for="primary_color"><input id="primary_color" name="primary_color" type="color" value="#df563e"><span>Primary</span></label><label class="color-input" for="secondary_color"><input id="secondary_color" name="secondary_color" type="color" value="#20362f"><span>Accent</span></label></div><div class="err" data-err="primary_color"></div><div class="err" data-err="secondary_color"></div></div>
      </div>
    </section>
    <section class="setting-card">
      <div class="setting-title"><span class="setting-icon sage">↗</span><div><h2>Ways to order</h2><p>Choose which options guests can use on your storefront.</p></div></div>
      <div class="channel-grid">
        <label class="channel-option"><span class="channel-icon">♧</span><span class="channel-copy"><b>Delivery</b><small>Guests can place delivery orders.</small></span><span class="toggle"><input type="checkbox" name="delivery_enabled"><i></i></span></label>
        <label class="channel-option"><span class="channel-icon">↗</span><span class="channel-copy"><b>Pickup</b><small>Guests can order ahead and collect.</small></span><span class="toggle"><input type="checkbox" name="pickup_enabled"><i></i></span></label>
        <label class="channel-option"><span class="channel-icon">▣</span><span class="channel-copy"><b>Dine in</b><small>Guests can order at their table by QR.</small></span><span class="toggle"><input type="checkbox" name="dinein_enabled"><i></i></span></label>
      </div>
    </section>
    <section class="setting-card hours-card">
      <div class="setting-title"><span class="setting-icon gold">◷</span><div><h2>Opening hours</h2><p>Set up to three service periods for each day. Overnight hours are supported.</p></div></div>
      <div class="hours-editor" id="hoursEditor" aria-busy="true"><div class="hours-loading">Loading opening hours…</div></div>
      <div class="err" data-err="hours"></div>
    </section>
    <div class="settings-bottom"><div class="msg" id="settingsMsg" role="status"></div><button class="btn" type="submit">Save restaurant settings</button></div>
  </form>
</main>
<?php page_foot('settings.js');
