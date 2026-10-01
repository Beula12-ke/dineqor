<?php
// Customer registration
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session();
require_csrf();

$in = json_input();
$name  = clean_str($in['full_name'] ?? '', 150);
$email = strtolower(clean_str($in['email'] ?? '', 150));
$phone = clean_str($in['phone'] ?? '', 30);
$pass  = (string)($in['password'] ?? '');

$errors = [];
if (mb_strlen($name) < 2) $errors['full_name'] = 'Enter your full name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
if (strlen($pass) < 8) $errors['password'] = 'Password must be at least 8 characters.';
if ($errors) json_error('Please fix the highlighted fields.', 422, ['fields' => $errors]);

$pdo = db();
$st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) json_error('An account with this email already exists.', 409, ['fields' => ['email' => 'Email already registered.']]);

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO users (email, password_hash, full_name, phone, user_type) VALUES (?,?,?,?, 'customer')")
        ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $name, $phone ?: null]);
    $uid = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO customers (user_id) VALUES (?)')->execute([$uid]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('register: ' . $e->getMessage());
    json_error('Something went wrong. Please try again.', 500);
}

login_user($uid);
log_activity('customer.registered', $uid);
json_out(['ok' => true, 'redirect' => BASE_URL . '/customer/dashboard.php', 'csrf' => csrf_token()], 201);
