<?php
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session();
require_csrf();

$in = json_input();
$email = strtolower(clean_str($in['email'] ?? '', 190));
$generic = 'If a customer account matches that email, a password reset link will be sent if email delivery is configured.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => true, 'message' => $generic]);

// Limit reset mail generation by client IP. Keep the response identical for privacy.
$ip = client_ip_bin();
$rate = db()->prepare("SELECT COUNT(*) FROM activity_logs WHERE action='auth.password_reset_requested' AND ip_address <=> ? AND created_at > (NOW() - INTERVAL 15 MINUTE)");
$rate->execute([$ip]);
if ((int)$rate->fetchColumn() >= 5) json_out(['ok' => true, 'message' => $generic]);

$st = db()->prepare("SELECT id, full_name, email FROM users WHERE email=? AND user_type='customer' AND status='active' AND deleted_at IS NULL LIMIT 1");
$st->execute([$email]);
$user = $st->fetch();
if ($user) {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $save = db()->prepare('INSERT INTO customer_password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash), expires_at=VALUES(expires_at), created_at=CURRENT_TIMESTAMP');
    $save->execute([(int)$user['id'], $tokenHash]);

    $resetUrl = rtrim(APP_ORIGIN, '/') . url('auth/reset-password.php') . '?token=' . rawurlencode($token);
    $subject = 'Reset your Dineqor password';
    $body = "Hello {$user['full_name']},\r\n\r\nWe received a request to reset your Dineqor customer account password. Use this one-time link within one hour:\r\n\r\n{$resetUrl}\r\n\r\nIf you did not request this, you can ignore this email. Your password will not change unless the link is used.\r\n";
    $headers = "From: Dineqor <" . MAIL_FROM . ">\r\nContent-Type: text/plain; charset=UTF-8";
    if (!mail($user['email'], $subject, $body, $headers)) {
        error_log('Customer password reset email could not be sent; configure PHP mail/SMTP.');
        db()->prepare('DELETE FROM customer_password_resets WHERE user_id=?')->execute([(int)$user['id']]);
    }
}
log_activity('auth.password_reset_requested', $user ? (int)$user['id'] : null, null, null, null, 'Customer password reset requested');
json_out(['ok' => true, 'message' => $generic]);
