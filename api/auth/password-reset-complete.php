<?php
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session();
require_csrf();

$in = json_input();
$token = strtolower((string)($in['token'] ?? ''));
$password = (string)($in['password'] ?? '');
$confirm = (string)($in['confirm_password'] ?? '');
$errors = [];
if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) $errors['password'] = 'This reset link is invalid or expired. Request a new one.';
if (strlen($password) < 8) $errors['password'] = 'Use at least 8 characters.';
if (!hash_equals($password, $confirm)) $errors['confirm_password'] = 'The passwords do not match.';
if ($errors) json_error('Please check your new password.', 422, ['fields' => $errors]);

$pdo = db();
try {
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT r.user_id FROM customer_password_resets r JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.expires_at>NOW() AND u.user_type=\'customer\' AND u.status=\'active\' AND u.deleted_at IS NULL FOR UPDATE');
    $find->execute([hash('sha256', $token)]);
    $userId = $find->fetchColumn();
    if (!$userId) {
        $pdo->rollBack();
        json_error('This reset link is invalid or expired. Request a new one.', 410);
    }
    $pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=NOW() WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), (int)$userId]);
    $pdo->prepare('DELETE FROM customer_password_resets WHERE user_id=?')->execute([(int)$userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Customer password reset failed: ' . $e->getMessage());
    json_error('Could not reset your password. Request a new link and try again.', 500);
}

json_out(['ok' => true, 'message' => 'Your password has been updated. You can now log in.', 'redirect' => BASE_URL . '/auth/login.php']);
