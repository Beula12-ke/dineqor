<?php
// Restaurant partner application -> status 'pending' until platform admin approves
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session();
require_csrf();

$in = json_input();
$rname = clean_str($in['restaurant_name'] ?? '', 150);
$owner = clean_str($in['owner_name'] ?? '', 150);
$email = strtolower(clean_str($in['email'] ?? '', 150));
$phone = clean_str($in['phone'] ?? '', 30);
$pass  = (string)($in['password'] ?? '');
$city  = clean_str($in['city'] ?? '', 80);
$cuisine = clean_str($in['cuisine'] ?? '', 80);
$desc  = clean_str($in['description'] ?? '', 2000);
$websiteUrl = normalize_https_website_url($in['website_url'] ?? '');

$errors = [];
if (mb_strlen($rname) < 2) $errors['restaurant_name'] = 'Enter the restaurant name.';
if (mb_strlen($owner) < 2) $errors['owner_name'] = 'Enter the owner name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
if ($phone === '') $errors['phone'] = 'Enter a phone number.';
if ($websiteUrl === false) $errors['website_url'] = 'Enter a complete secure website address, such as https://restaurant.com.';
if (strlen($pass) < 8) $errors['password'] = 'Password must be at least 8 characters.';
if ($errors) json_error('Please fix the highlighted fields.', 422, ['fields' => $errors]);

$pdo = db();
$st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) json_error('An account with this email already exists.', 409, ['fields' => ['email' => 'Email already registered.']]);

try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO users (email, password_hash, full_name, phone, user_type) VALUES (?,?,?,?, 'restaurant_owner')")
        ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $owner, $phone]);
    $uid = (int)$pdo->lastInsertId();

    // unique slug
    $base = make_slug($rname); $slug = $base; $i = 1;
    $chk = $pdo->prepare('SELECT 1 FROM restaurants WHERE slug = ?');
    while (true) {
        $chk->execute([$slug]);
        if (!$chk->fetch()) break;
        $slug = $base . '-' . (++$i);
    }

    $pdo->prepare("INSERT INTO restaurants (slug, name, owner_user_id, status, cuisine, description, website_url, phone, email, city, country)
                   VALUES (?,?,?, 'pending', ?,?,?,?,?,?, 'Kenya')")
        ->execute([$slug, $rname, $uid, $cuisine ?: null, $desc ?: null, $websiteUrl ?: null, $phone, $email, $city ?: null]);
    $rid = (int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO restaurant_onboarding_progress (restaurant_id, current_step) VALUES (?, 1)')->execute([$rid]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('restaurant register: ' . $e->getMessage());
    json_error('Something went wrong. Please try again.', 500);
}

// Create restaurant folder structure for uploads
foreach (['', 'products', 'branding', 'gallery'] as $sub) {
    $dir = UPLOAD_DIR . $slug . ($sub ? '/' . $sub : '');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
}

log_activity('restaurant.applied', $uid, $rid, 'restaurant', $rid, "Application: $rname");
json_out(['ok' => true, 'message' => 'Application submitted. We will review it and email you once approved.'], 201);
