<?php
// Restaurant details and hours. Tenant identity always comes from the authenticated server session.
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('manage_settings');
$rid = tenant_id($u);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = $pdo->prepare('SELECT name,cuisine,description,website_url,phone,email,address,city,country,primary_color,secondary_color,price_range,delivery_enabled,pickup_enabled,dinein_enabled,status FROM restaurants WHERE id=? AND deleted_at IS NULL');
    $st->execute([$rid]); $restaurant = $st->fetch();
    if (!$restaurant) json_error('Restaurant not found.', 404);
    $q = $pdo->prepare('SELECT weekday,open_time,close_time,is_closed FROM restaurant_hours WHERE restaurant_id=? ORDER BY weekday,open_time');
    $q->execute([$rid]); $rows = $q->fetchAll();
    $days = [];
    for ($day = 0; $day <= 6; $day++) $days[$day] = ['weekday' => $day, 'is_closed' => true, 'periods' => []];
    foreach ($rows as $row) {
        $day = (int)$row['weekday'];
        if ($row['is_closed']) continue;
        $days[$day]['is_closed'] = false;
        if ($row['open_time'] !== null && $row['close_time'] !== null) {
            $days[$day]['periods'][] = ['open_time' => substr($row['open_time'], 0, 5), 'close_time' => substr($row['close_time'], 0, 5)];
        }
    }
    foreach ($days as &$day) if (!$day['periods']) $day['is_closed'] = true;
    unset($day);
    json_out(['ok' => true, 'restaurant' => $restaurant, 'hours' => array_values($days), 'csrf' => csrf_token()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
start_secure_session(); require_csrf();
$in = json_input();
$name = clean_str($in['name'] ?? '', 150);
$cuisine = clean_str($in['cuisine'] ?? '', 80);
$description = clean_str($in['description'] ?? '', 2000);
$websiteUrl = normalize_https_website_url($in['website_url'] ?? '');
$phone = clean_str($in['phone'] ?? '', 30);
$email = strtolower(clean_str($in['email'] ?? '', 150));
$address = clean_str($in['address'] ?? '', 500);
$city = clean_str($in['city'] ?? '', 80);
$country = clean_str($in['country'] ?? '', 80);
$primary = strtoupper(clean_str($in['primary_color'] ?? '', 7));
$secondary = strtoupper(clean_str($in['secondary_color'] ?? '', 7));
$priceRaw = $in['price_range'] ?? '';
$errors = [];
if (mb_strlen($name) < 2) $errors['name'] = 'Restaurant name must be at least 2 characters.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
if ($websiteUrl === false) $errors['website_url'] = 'Enter a complete secure website address, such as https://restaurant.com.';
if ($primary !== '' && !preg_match('/^#[0-9A-F]{6}$/', $primary)) $errors['primary_color'] = 'Choose a valid brand color.';
if ($secondary !== '' && !preg_match('/^#[0-9A-F]{6}$/', $secondary)) $errors['secondary_color'] = 'Choose a valid accent color.';
$priceRange = null;
if ($priceRaw !== '' && $priceRaw !== null) {
    if (!in_array((string)$priceRaw, ['1','2','3','4'], true)) $errors['price_range'] = 'Choose a price range from 1 to 4.';
    else $priceRange = (int)$priceRaw;
}

$rawHours = $in['hours'] ?? null;
$hours = [];
if (!is_array($rawHours) || count($rawHours) !== 7) $errors['hours'] = 'Set opening hours for all seven days.';
else {
    $seen = [];
    foreach ($rawHours as $day) {
        if (!is_array($day)) { $errors['hours'] = 'Opening hours are invalid.'; break; }
        $weekday = filter_var($day['weekday'] ?? null, FILTER_VALIDATE_INT);
        if ($weekday === false || $weekday < 0 || $weekday > 6 || isset($seen[$weekday])) { $errors['hours'] = 'Opening hours must include each day once.'; break; }
        $seen[$weekday] = true;
        $closed = !empty($day['is_closed']);
        $periods = $closed ? [] : ($day['periods'] ?? null);
        if (!$closed && (!is_array($periods) || count($periods) < 1 || count($periods) > 3)) { $errors['hours'] = 'Each open day needs between one and three opening periods.'; break; }
        $cleanPeriods = []; $minuteRanges = [];
        foreach ($periods ?: [] as $period) {
            $open = (string)($period['open_time'] ?? ''); $close = (string)($period['close_time'] ?? '');
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $open) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $close) || $open === $close) {
                $errors['hours'] = 'Choose valid opening and closing times for every period.'; break 2;
            }
            [$oh, $om] = array_map('intval', explode(':', $open)); [$ch, $cm] = array_map('intval', explode(':', $close));
            $startMinute = $oh * 60 + $om; $endMinute = $ch * 60 + $cm;
            if ($endMinute <= $startMinute) $endMinute += 1440;
            foreach ($minuteRanges as [$previousStart, $previousEnd]) {
                foreach ([-1440, 0, 1440] as $shift) {
                    if ($startMinute + $shift < $previousEnd && $previousStart < $endMinute + $shift) {
                        $errors['hours'] = 'Opening periods for the same day cannot overlap.'; break 3;
                    }
                }
            }
            $minuteRanges[] = [$startMinute, $endMinute];
            $cleanPeriods[] = ['open_time' => $open . ':00', 'close_time' => $close . ':00'];
        }
        $hours[] = ['weekday' => $weekday, 'is_closed' => $closed, 'periods' => $cleanPeriods];
    }
    if (!$errors && count($seen) !== 7) $errors['hours'] = 'Opening hours must include each day once.';
}
if ($errors) json_error('Please review the highlighted settings.', 422, ['fields' => $errors]);

$delivery = empty($in['delivery_enabled']) ? 0 : 1;
$pickup = empty($in['pickup_enabled']) ? 0 : 1;
$dinein = empty($in['dinein_enabled']) ? 0 : 1;
try {
    $pdo->beginTransaction();
    $update = $pdo->prepare('UPDATE restaurants SET name=?,cuisine=?,description=?,website_url=?,phone=?,email=?,address=?,city=?,country=?,primary_color=?,secondary_color=?,price_range=?,delivery_enabled=?,pickup_enabled=?,dinein_enabled=? WHERE id=? AND deleted_at IS NULL');
    $update->execute([$name, $cuisine ?: null, $description ?: null, $websiteUrl ?: null, $phone ?: null, $email ?: null, $address ?: null, $city ?: null, $country ?: null,
        $primary ?: '#DF563E', $secondary ?: '#20362F', $priceRange, $delivery, $pickup, $dinein, $rid]);
    $pdo->prepare('DELETE FROM restaurant_hours WHERE restaurant_id=?')->execute([$rid]);
    $insert = $pdo->prepare('INSERT INTO restaurant_hours (restaurant_id,weekday,open_time,close_time,is_closed) VALUES (?,?,?,?,?)');
    foreach ($hours as $day) {
        if ($day['is_closed']) { $insert->execute([$rid, $day['weekday'], null, null, 1]); continue; }
        foreach ($day['periods'] as $period) $insert->execute([$rid, $day['weekday'], $period['open_time'], $period['close_time'], 0]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('restaurant settings: ' . $e->getMessage());
    json_error('Could not save restaurant settings. Please try again.', 500);
}
log_activity('restaurant.settings_updated', (int)$u['id'], $rid, 'restaurant', $rid, 'Restaurant profile and opening hours updated');
json_out(['ok' => true, 'message' => 'Restaurant settings saved.']);
