<?php
require_once __DIR__ . '/../../includes/auth.php';
$u = require_user_type('customer');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
start_secure_session(); require_csrf();
$in = json_input();
$reservationId = (int)($in['id'] ?? 0);
if ($reservationId < 1 || ($in['action'] ?? '') !== 'cancel') json_error('Choose a reservation to cancel.', 422);

$userId = (int)$u['id'];
$find = db()->prepare('SELECT restaurant_id FROM reservations WHERE id=? AND customer_user_id=? LIMIT 1');
$find->execute([$reservationId, $userId]);
$restaurantId = $find->fetchColumn();
if (!$restaurantId) json_error('Reservation not found.', 404);
$cancel = db()->prepare("UPDATE reservations SET status='cancelled',cancelled_at=NOW()
    WHERE id=? AND customer_user_id=? AND status IN ('pending','confirmed')
    AND (reservation_date > CURDATE() OR (reservation_date=CURDATE() AND reservation_time > CURTIME()))");
$cancel->execute([$reservationId, $userId]);
if (!$cancel->rowCount()) json_error('This reservation has already started or can no longer be cancelled online.', 409);
log_activity('reservation.customer_cancelled', $userId, (int)$restaurantId, 'reservation', $reservationId);
json_out(['ok' => true, 'status' => 'cancelled', 'message' => 'Your reservation has been cancelled.']);
