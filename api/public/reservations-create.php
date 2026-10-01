<?php
// Guest/customer reservation request. Availability is checked on the SERVER: capacity, hours, and a basic overlap limit.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/tenant.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session(); require_csrf();

$in = json_input();
$r = resolve_public_restaurant(clean_str($in['slug'] ?? '', 80) ?: null);
if (!$r) json_error('Restaurant not found.', 404);
$rid = (int)$r['id'];

$name  = clean_str($in['name'] ?? '', 150);
$phone = clean_str($in['phone'] ?? '', 30);
$email = strtolower(clean_str($in['email'] ?? '', 150));
$date  = clean_str($in['date'] ?? '', 10);
$time  = clean_str($in['time'] ?? '', 5);
$guests = (int)($in['guests'] ?? 0);
$note  = clean_str($in['special_request'] ?? '', 500);

$errors = [];
if (mb_strlen($name) < 2) $errors['name'] = 'Enter your name.';
if (!preg_match('/^\+?[0-9 ()-]{9,20}$/', $phone)) $errors['phone'] = 'Enter a valid phone number.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email or leave it blank.';
if ($guests < 1 || $guests > 30) $errors['guests'] = 'Enter the number of guests (1-30).';
$dt = DateTime::createFromFormat('Y-m-d H:i', "$date $time");
$now = new DateTime('now');
if (!$dt || $dt->format('Y-m-d') !== $date) $errors['date'] = 'Choose a valid date.';
elseif ($dt < $now) $errors['date'] = 'Choose a date and time in the future.';
elseif ($dt > (clone $now)->modify('+90 days')) $errors['date'] = 'Reservations can only be made up to 90 days ahead.';
if ($errors) json_error('Please fix the highlighted fields.', 422, ['fields' => $errors]);

// Restaurant must be open at that time on that weekday
$hoursSt = db()->prepare('SELECT weekday, open_time, close_time, is_closed FROM restaurant_hours WHERE restaurant_id = ? AND weekday = ?');
$hoursSt->execute([$rid, (int)$dt->format('w')]);
$open = false;
foreach ($hoursSt->fetchAll() as $h) {
    if ($h['is_closed']) continue;
    if ($time >= substr($h['open_time'], 0, 5) && $time <= substr($h['close_time'], 0, 5)) { $open = true; break; }
}
if ($hoursSt->rowCount() && !$open) json_error('The restaurant is not open at that time. Please pick another slot.', 422);

// Capacity check: total guests already pending/confirmed within 90 minutes of the requested slot must not exceed total seating
$capSt = db()->prepare("SELECT COALESCE(SUM(capacity),0) FROM restaurant_tables WHERE restaurant_id = ?");
$capSt->execute([$rid]);
$totalSeats = (int)$capSt->fetchColumn();
if ($totalSeats > 0) {
    $bookedSt = db()->prepare("SELECT COALESCE(SUM(guests),0) FROM reservations
        WHERE restaurant_id = ? AND status IN ('pending','confirmed') AND reservation_date = ?
        AND ABS(TIMESTAMPDIFF(MINUTE, TIMESTAMP(reservation_date, reservation_time), ?)) < 90");
    $bookedSt->execute([$rid, $date, $dt->format('Y-m-d H:i:s')]);
    $booked = (int)$bookedSt->fetchColumn();
    if ($booked + $guests > $totalSeats) json_error('That time is fully booked. Please choose another time.', 409);
}

$u = current_user();
db()->prepare('INSERT INTO reservations (restaurant_id, customer_user_id, name, phone, email, reservation_date, reservation_time, guests, special_request)
               VALUES (?,?,?,?,?,?,?,?,?)')
   ->execute([$rid, ($u && $u['user_type'] === 'customer') ? (int)$u['id'] : null, $name, $phone, $email ?: null,
              $date, $time, $guests, $note ?: null]);
$id = (int)db()->lastInsertId();
log_activity('reservation.created', $u['id'] ?? null, $rid, 'reservation', $id, "$name, $guests guests, $date $time");
json_out(['ok' => true, 'message' => 'Reservation requested. The restaurant will confirm it shortly.'], 201);
