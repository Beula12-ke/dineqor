<?php
require_once __DIR__ . '/../../includes/auth.php';
$admin = require_user_type('platform_admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $pdo->query('SELECT setting_key, setting_value FROM platform_settings')->fetchAll();
    $settings = [];
    foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];
    json_out(['ok' => true, 'csrf' => csrf_token(), 'settings' => $settings]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
start_secure_session(); require_csrf();
$in = json_input();
$action = (string)($in['action'] ?? '');

if ($action === 'save_platform') {
    $name = clean_str($in['platform_name'] ?? '', 80);
    $email = strtolower(clean_str($in['support_email'] ?? '', 190));
    $phone = clean_str($in['support_phone'] ?? '', 30);
    $errors = [];
    if (mb_strlen($name) < 2) $errors['platform_name'] = 'Enter a platform name with at least 2 characters.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['support_email'] = 'Enter a valid support email.';
    if ($phone !== '' && !preg_match('/^[0-9+().\-\s]{7,30}$/', $phone)) $errors['support_phone'] = 'Enter a valid support phone number.';
    if ($errors) json_error('Please check the platform details.', 422, ['fields' => $errors]);

    $save = $pdo->prepare('INSERT INTO platform_settings (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
    foreach (['platform_name' => $name, 'support_email' => $email, 'support_phone' => $phone] as $key => $value) $save->execute([$key, $value, (int)$admin['id']]);
    log_activity('platform.settings_updated', (int)$admin['id'], null, 'platform_settings');
    json_out(['ok' => true, 'message' => 'Platform details saved.']);
}

if ($action === 'save_account') {
    $name = clean_str($in['full_name'] ?? '', 150);
    $email = strtolower(clean_str($in['email'] ?? '', 190));
    $phone = clean_str($in['phone'] ?? '', 30);
    $current = (string)($in['current_password'] ?? '');
    $new = (string)($in['new_password'] ?? '');
    $confirm = (string)($in['confirm_password'] ?? '');
    $errors = [];
    if (mb_strlen($name) < 2) $errors['full_name'] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if ($phone !== '' && !preg_match('/^[0-9+().\-\s]{7,30}$/', $phone)) $errors['phone'] = 'Enter a valid phone number.';
    if ($new !== '' && strlen($new) < 8) $errors['new_password'] = 'Use at least 8 characters.';
    if ($new !== $confirm) $errors['confirm_password'] = 'The passwords do not match.';
    if (($new !== '' || $email !== $admin['email']) && $current === '') $errors['current_password'] = 'Enter your current password to change your email or password.';
    if ($errors) json_error('Please check your account details.', 422, ['fields' => $errors]);

    $existing = $pdo->prepare('SELECT password_hash FROM users WHERE id=? AND deleted_at IS NULL AND user_type=\'platform_admin\'');
    $existing->execute([(int)$admin['id']]); $account = $existing->fetch();
    if (!$account) json_error('Your administrator account could not be found.', 401);
    if ($current !== '' && !password_verify($current, (string)$account['password_hash'])) json_error('Your current password is incorrect.', 422, ['fields' => ['current_password' => 'Check your current password.']]);
    $duplicate = $pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? AND deleted_at IS NULL LIMIT 1');
    $duplicate->execute([$email, (int)$admin['id']]);
    if ($duplicate->fetchColumn()) json_error('That email is already used by another account.', 409, ['fields' => ['email' => 'Email already registered.']]);

    if ($new !== '') {
        $pdo->prepare('UPDATE users SET full_name=?,email=?,phone=?,password_hash=?,password_changed_at=NOW() WHERE id=?')
            ->execute([$name, $email, $phone ?: null, password_hash($new, PASSWORD_DEFAULT), (int)$admin['id']]);
    } else {
        $pdo->prepare('UPDATE users SET full_name=?,email=?,phone=? WHERE id=?')->execute([$name, $email, $phone ?: null, (int)$admin['id']]);
    }
    log_activity('platform.admin_account_updated', (int)$admin['id']);
    json_out(['ok' => true, 'message' => 'Administrator account saved.']);
}

json_error('Unknown settings action.', 422);
