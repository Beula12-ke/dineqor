<?php
// GET: upcoming reservations for the logged-in restaurant.  POST {action: confirm|reject|cancel|complete|no_show, id}
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('manage_reservations');
$rid = tenant_id($u);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session(); require_csrf();
    $in = json_input();
    $id = (int)($in['id'] ?? 0);
    $action = (string)($in['action'] ?? '');
    $map = ['confirm' => 'confirmed', 'reject' => 'rejected', 'cancel' => 'cancelled', 'complete' => 'completed', 'no_show' => 'no_show'];
    if (!isset($map[$action])) json_error('Invalid request.', 422);
    $to = $map[$action];
    $extra = $to === 'confirmed' ? ', confirmed_at = NOW()' : (in_array($to, ['cancelled', 'rejected'], true) ? ', cancelled_at = NOW()' : '');
    $st = $pdo->prepare("UPDATE reservations SET status = ?$extra WHERE id = ? AND restaurant_id = ? AND status IN ('pending','confirmed')");
    $st->execute([$to, $id, $rid]);
    if (!$st->rowCount()) json_error('Reservation not found or already finalised.', 404);
    log_activity('reservation.' . $action, (int)$u['id'], $rid, 'reservation', $id);
    json_out(['ok' => true, 'status' => $to]);
}

$st = $pdo->prepare("SELECT id, name, phone, email, reservation_date, reservation_time, guests, special_request, status, created_at
    FROM reservations WHERE restaurant_id = ? AND (status IN ('pending','confirmed') OR reservation_date = CURDATE())
    ORDER BY reservation_date, reservation_time LIMIT 200");
$st->execute([$rid]);
json_out(['ok' => true, 'csrf' => csrf_token(), 'reservations' => $st->fetchAll()]);
