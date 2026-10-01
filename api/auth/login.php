<?php
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session();
require_csrf();

$in = json_input();
$email = strtolower(clean_str($in['email'] ?? '', 150));
$pass  = (string)($in['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') json_error('Enter a valid email and password.', 422);

// Rate limit: failed logins from this IP in the lock window
$ip = client_ip_bin();
$st = db()->prepare("SELECT COUNT(*) FROM activity_logs WHERE action='auth.login_failed'
                     AND ip_address <=> ? AND created_at > (NOW() - INTERVAL ? SECOND)");
$st->execute([$ip, LOGIN_LOCK_SECONDS]);
if ((int)$st->fetchColumn() >= MAX_LOGIN_ATTEMPTS) {
    json_error('Too many failed attempts. Try again in a few minutes.', 429);
}

$st = db()->prepare('SELECT id, password_hash, status, user_type, password_expires_at FROM users WHERE email = ? AND deleted_at IS NULL');
$st->execute([$email]);
$row = $st->fetch();

// Constant-ish behaviour: always run a verify to avoid user enumeration by timing
$hash = $row['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
$ok = password_verify($pass, $hash) && $row && $row['status'] === 'active';

if (!$ok) {
    log_activity('auth.login_failed', $row['id'] ?? null, null, null, null, 'Failed login for ' . $email);
    json_error('Incorrect email or password.', 401);
}

if ($row['user_type'] === 'restaurant_staff' && !empty($row['password_expires_at'])
    && strtotime((string)$row['password_expires_at']) <= time()) {
    log_activity('auth.login_expired_password', (int)$row['id'], null, null, null, 'Expired team password login attempt');
    json_error('Your team password has expired after 90 days. Ask the restaurant owner or manager to set a new one.', 403, ['password_expired' => true]);
}

if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
}

login_user((int)$row['id']);
unset($GLOBALS['__cu']);
log_activity('auth.login', (int)$row['id']);
$u = current_user();
json_out(['ok' => true, 'redirect' => dashboard_for($u), 'csrf' => csrf_token()]);
