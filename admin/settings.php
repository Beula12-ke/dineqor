<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['platform_admin']);
page_head('Admin settings'); nav_bar($u); ?>
<main class="wrap admin-settings-page">
  <header class="inventory-heading"><div><div class="eyebrow">PLATFORM ADMINISTRATION</div><h1>Settings</h1><p>Manage Dineqor’s public contact details and your administrator account.</p></div></header>
  <section class="dash-panel admin-settings-panel">
    <div class="panel-heading"><div><h2>Platform details</h2><p>These details appear on the public site so customers and restaurant partners can identify and contact Dineqor.</p></div></div>
    <form id="platformSettingsForm" class="admin-settings-form" novalidate>
      <label>Platform name<input name="platform_name" maxlength="80" required><span class="err" data-err="platform_name"></span></label>
      <label>Support email<input name="support_email" type="email" maxlength="190" placeholder="support@example.com"><span class="err" data-err="support_email"></span></label>
      <label>Support phone<input name="support_phone" type="tel" maxlength="30" placeholder="+254 700 000 000"><span class="err" data-err="support_phone"></span></label>
      <div class="admin-settings-actions"><span class="msg" id="platformSettingsMessage" role="status"></span><button class="btn sm" type="submit">Save platform details</button></div>
    </form>
  </section>

  <section class="dash-panel admin-settings-panel">
    <div class="panel-heading"><div><h2>Administrator account</h2><p>Update the name and contact details for your platform administrator sign-in.</p></div></div>
    <form id="adminAccountForm" class="admin-settings-form" novalidate>
      <label>Full name<input name="full_name" maxlength="150" required value="<?= e($u['full_name']) ?>"><span class="err" data-err="full_name"></span></label>
      <label>Email address<input name="email" type="email" maxlength="190" required value="<?= e($u['email']) ?>"><span class="err" data-err="email"></span></label>
      <label>Phone number<input name="phone" type="tel" maxlength="30" value="<?= e($u['phone'] ?? '') ?>"><span class="err" data-err="phone"></span></label>
      <p class="admin-settings-hint">Leave the password fields blank to keep your current password. To change it, enter your current password and a new password of at least 8 characters.</p>
      <label>Current password<input name="current_password" type="password" autocomplete="current-password"><span class="err" data-err="current_password"></span></label>
      <label>New password<input name="new_password" type="password" autocomplete="new-password" minlength="8"><span class="err" data-err="new_password"></span></label>
      <label>Confirm new password<input name="confirm_password" type="password" autocomplete="new-password" minlength="8"><span class="err" data-err="confirm_password"></span></label>
      <div class="admin-settings-actions"><span class="msg" id="adminAccountMessage" role="status"></span><button class="btn sm" type="submit">Save account</button></div>
    </form>
  </section>
</main>
<?php page_foot('admin-settings.js');
