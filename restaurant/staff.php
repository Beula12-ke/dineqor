<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['restaurant_owner', 'restaurant_staff']);
if (!has_permission($u, 'manage_staff')) { http_response_code(403); page_head('Team access'); nav_bar($u); echo '<main class="wrap"><div class="empty"><b>You do not have access to team management.</b>Ask your restaurant owner to review your permissions.</div></main>'; page_foot(); exit; }
page_head('Team management'); nav_bar($u); ?>
<main class="wrap team-page">
  <header class="team-heading"><div><div class="eyebrow dark-eyebrow">RESTAURANT OPERATIONS</div><h1>Your team</h1><p>Add team accounts, assign a role, and manage access to this restaurant.</p></div><span class="team-count" id="teamCount">Loading…</span></header>
  <section class="setting-card team-add-card">
    <div class="setting-title"><span class="setting-icon coral">＋</span><div><h2>Add a team member</h2><p>Create a sign-in account for someone who does not already have a Dineqor account.</p></div></div>
    <form id="staffForm" novalidate>
      <div class="setting-grid">
        <div class="setting-field"><label for="staffName">Full name</label><input id="staffName" name="full_name" maxlength="150" required autocomplete="name"><div class="err" data-err="full_name"></div></div>
        <div class="setting-field"><label for="staffEmail">Email</label><input id="staffEmail" name="email" type="email" maxlength="150" required autocomplete="email"><div class="err" data-err="email"></div></div>
        <div class="setting-field"><label for="staffPhone">Phone <span class="optional-label">Optional</span></label><input id="staffPhone" name="phone" type="tel" maxlength="30" autocomplete="tel"></div>
        <div class="setting-field"><label for="staffRole">Role</label><select id="staffRole" name="role" required><option value="manager">Manager</option><option value="cashier">Cashier</option><option value="chef">Chef</option><option value="waiter">Waiter</option><option value="delivery">Delivery</option><option value="inventory">Inventory</option></select><div class="err" data-err="role"></div></div>
        <div class="setting-field"><label for="staffPassword">Temporary password</label><input id="staffPassword" name="password" type="password" minlength="8" autocomplete="new-password" required><small class="field-hint">At least 8 characters. It expires after 90 days. An owner or manager can set a new password when it expires. Share it securely.</small><div class="err" data-err="password"></div></div>
      </div>
      <div class="team-form-bottom"><div class="msg" id="staffFormMsg" role="status"></div><button class="btn" type="submit">Add team member</button></div>
    </form>
  </section>
  <section class="dash-panel team-list-panel"><div class="panel-heading"><div><h2>Team members</h2><p>Passwords expire after 90 days. Owners and managers can set replacements here.</p></div><button class="btn sm ghost" type="button" id="refreshTeam">Refresh</button></div><div class="team-list" id="teamList" aria-live="polite"><div class="inventory-loading">Loading team members…</div></div></section>
  <dialog class="card team-password-dialog" id="resetPasswordDialog" aria-labelledby="resetPasswordTitle">
    <form id="resetPasswordForm" novalidate>
      <div class="team-password-dialog-head"><span class="setting-icon coral">↻</span><div><h2 id="resetPasswordTitle">Set a new password</h2><p id="resetPasswordPerson">Team member</p></div></div>
      <label for="resetTemporaryPassword">New password</label><input id="resetTemporaryPassword" name="password" type="password" minlength="8" autocomplete="new-password" required><small class="field-hint">Use at least 8 characters. This password is active immediately and expires after 90 days. Share it securely; the member’s existing sessions will be signed out.</small><div class="err" data-err="password"></div>
      <div class="msg" id="resetPasswordMsg" role="status"></div>
      <div class="team-password-dialog-actions"><button class="btn sm ghost" type="button" id="cancelResetPassword">Cancel</button><button class="btn sm" type="submit">Save new password</button></div>
    </form>
  </dialog>
</main>
<?php page_foot('staff.js');
