<?php
require_once __DIR__ . '/../../includes/auth.php';
$u = require_user_type('customer');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
start_secure_session(); require_csrf();
$in = json_input();
$name = clean_str($in['full_name'] ?? '', 150);
$email = strtolower(clean_str($in['email'] ?? '', 190));
$phone = clean_str($in['phone'] ?? '', 30);
$defaultDeliveryAddress = clean_str($in['default_delivery_address'] ?? '', 500);
$currentPassword = (string)($in['current_password'] ?? '');
$newPassword = (string)($in['new_password'] ?? '');
$errors = [];
if (mb_strlen($name) < 2) $errors['full_name'] = 'Enter your name (at least 2 characters).';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
if ($phone !== '' && !preg_match('/^[0-9+().\-\s]{7,30}$/', $phone)) $errors['phone'] = 'Enter a valid phone number.';
if ($currentPassword !== '' || $newPassword !== '') {
    if ($currentPassword === '') $errors['current_password'] = 'Enter your current password to change it.';
    if (strlen($newPassword) < 8) $errors['new_password'] = 'Your new password must be at least 8 characters.';
}
if ($errors) json_error('Please check the highlighted fields.', 422, ['fields' => $errors]);

$existing = db()->prepare('SELECT email,password_hash FROM users WHERE id=? AND deleted_at IS NULL');
$existing->execute([(int)$u['id']]); $account = $existing->fetch();
if (!$account) json_error('Your account could not be found. Please sign in again.', 401);
$emailCheck = db()->prepare('SELECT id FROM users WHERE email=? AND id<>? AND deleted_at IS NULL LIMIT 1');
$emailCheck->execute([$email, (int)$u['id']]);
if ($emailCheck->fetchColumn()) json_error('That email address is already being used by another account.', 409, ['fields' => ['email' => 'Email already registered.']]);
if ($currentPassword !== '' && !password_verify($currentPassword, $account['password_hash'])) {
    json_error('Your current password is incorrect.', 422, ['fields' => ['current_password' => 'Check your current password.']]);
}

$pdo = db();
try {
    $pdo->beginTransaction();
    if ($newPassword !== '') {
        $pdo->prepare('UPDATE users SET full_name=?,email=?,phone=?,password_hash=? WHERE id=?')
            ->execute([$name, $email, $phone ?: null, password_hash($newPassword, PASSWORD_DEFAULT), (int)$u['id']]);
    } else {
        $pdo->prepare('UPDATE users SET full_name=?,email=?,phone=? WHERE id=?')
            ->execute([$name, $email, $phone ?: null, (int)$u['id']]);
    }
    $pdo->prepare('INSERT INTO customers (user_id,default_delivery_address) VALUES (?,?) ON DUPLICATE KEY UPDATE default_delivery_address=VALUES(default_delivery_address)')
        ->execute([(int)$u['id'], $defaultDeliveryAddress ?: null]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('customer profile update: ' . $e->getMessage());
    json_error('Could not save your profile. Please try again.', 500);
}
log_activity('customer.profile_updated', (int)$u['id']);
json_out(['ok' => true, 'message' => 'Your account details have been saved.']);
