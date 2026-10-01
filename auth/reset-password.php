<?php
require_once __DIR__ . '/../includes/page.php';
$token = strtolower((string)($_GET['token'] ?? ''));
$validFormat = (bool)preg_match('/\A[a-f0-9]{64}\z/', $token);
page_head('Reset your password');
?>
<div class="auth-wrap"><form class="card" id="resetForm" novalidate>
  <a class="logo" href="<?= e(url()) ?>">Dine<span>qor</span></a>
  <h1>Choose a new password</h1>
  <p class="sub">Use at least 8 characters. This reset link expires after one hour and can only be used once.</p>
  <?php if (!$validFormat): ?>
    <div class="msg error">This reset link is invalid or incomplete. Request a new link.</div>
    <div class="alt"><a href="forgot-password.php">Request a reset link</a></div>
  <?php else: ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
    <div class="err" data-err="password"></div>
    <label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
    <div class="err" data-err="confirm_password"></div>
    <button class="btn" type="submit">Save new password</button><div class="msg" id="msg" role="status" aria-live="polite"></div>
    <div class="alt"><a href="login.php">Back to log in</a></div>
  <?php endif; ?>
</form></div>
<?php page_foot(); ?>
<?php if ($validFormat): ?>
<script>
document.getElementById('resetForm').addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget, button = form.querySelector('.btn'), message = document.getElementById('msg');
  button.disabled = true; message.className = 'msg'; message.textContent = '';
  showFieldErrors(form);
  try {
    const result = await api('auth/password-reset-complete.php', { method: 'POST', body: formData(form) });
    if (result.ok) { message.className = 'msg ok'; message.textContent = result.message; setTimeout(() => location.href = result.redirect, 1200); }
    else { message.className = 'msg error'; message.textContent = result.error || 'Could not reset your password.'; showFieldErrors(form, result.fields || {}); button.disabled = false; }
  } catch (error) {
    message.className = 'msg error'; message.textContent = 'Could not reach the server. Please try again.'; button.disabled = false;
  }
});
</script>
<?php endif; ?>
