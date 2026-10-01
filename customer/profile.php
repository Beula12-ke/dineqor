<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['customer']);
$addressQuery = db()->prepare('SELECT default_delivery_address FROM customers WHERE user_id=?');
$addressQuery->execute([(int)$u['id']]);
$defaultDeliveryAddress = (string)($addressQuery->fetchColumn() ?: '');
page_head('Profile & security'); nav_bar($u); ?>
<main class="customer-home customer-workspace customer-list-page">
  <header class="customer-heading"><div><div class="eyebrow">YOUR ACCOUNT</div><h1>Profile & security</h1><p>Manage your personal details and keep your account secure.</p></div></header>
  <form class="profile-editor" id="profileForm" novalidate>
    <section class="profile-card"><div class="profile-card-heading"><span class="profile-card-icon">♙</span><div><h2>Personal details</h2><p>These details are used for your Dineqor account.</p></div></div>
      <div class="profile-grid"><div class="setting-field"><label for="full_name">Full name</label><input id="full_name" name="full_name" autocomplete="name" value="<?= e($u['full_name']) ?>" required maxlength="150"><div class="err" data-err="full_name"></div></div>
        <div class="setting-field"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" value="<?= e($u['email']) ?>" required maxlength="190"><div class="err" data-err="email"></div></div>
        <div class="setting-field profile-wide"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" autocomplete="tel" value="<?= e($u['phone'] ?? '') ?>" maxlength="30" placeholder="e.g. +254 700 000 000"><small class="field-hint">Used by restaurants if they need to reach you about an order.</small><div class="err" data-err="phone"></div></div>
        <div class="setting-field profile-wide"><label for="default_delivery_address">Default delivery address</label><textarea id="default_delivery_address" name="default_delivery_address" rows="3" maxlength="500" autocomplete="street-address" placeholder="Building, street, area and helpful directions"><?= e($defaultDeliveryAddress) ?></textarea><small class="field-hint">Prefills delivery checkout. You can change it for each order.</small><div class="err" data-err="default_delivery_address"></div></div>
      </div>
    </section>
    <section class="profile-card"><div class="profile-card-heading"><span class="profile-card-icon sage">⌑</span><div><h2>Change password</h2><p>Leave these fields blank if you don’t want to change your password.</p></div></div>
      <div class="profile-grid"><div class="setting-field profile-wide"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password"><div class="err" data-err="current_password"></div></div>
        <div class="setting-field"><label for="new_password">New password</label><input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="8"><small class="field-hint">Use at least 8 characters.</small><div class="err" data-err="new_password"></div></div>
        <div class="setting-field"><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8"><div class="err" data-err="confirm_password"></div></div>
      </div>
    </section>
    <div class="profile-actions"><div class="msg" id="profileMsg" role="status"></div><button class="btn" type="submit">Save changes</button></div>
  </form>
</main>
<?php page_foot('profile.js');
