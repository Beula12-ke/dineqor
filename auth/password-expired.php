<?php
require_once __DIR__ . '/../includes/page.php';
$u = current_user();
if ($u && $u['user_type'] !== 'restaurant_staff') { header('Location: ' . dashboard_for($u)); exit; }
page_head('Team password expired', '', 'password-expired-page');
?>
<main class="auth-wrap password-expired-wrap">
  <section class="card password-expired-card">
    <a class="logo" href="<?= e(url()) ?>">Dine<span>qor</span></a>
    <div class="password-expired-mark" aria-hidden="true">↻</div>
    <div class="eyebrow dark-eyebrow">TEAM ACCOUNT SECURITY</div>
    <h1>Your password has expired</h1>
    <p class="sub">For security, team passwords expire every 90 days. Contact your restaurant owner or manager; they need to set your new password before you can sign in again.</p>
    <div class="password-expired-help"><b>What to do</b><span>Ask the owner or manager to open <strong>Team</strong>, choose your account, and set a new password. They should share it with you securely.</span></div>
    <button class="btn password-expired-logout" type="button" onclick="logout()">Sign out</button>
    <a class="password-expired-home" href="<?= e(url('auth/login.php')) ?>">Back to sign in</a>
  </section>
</main>
<?php page_foot();
