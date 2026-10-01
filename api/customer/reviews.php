<?php
require_once __DIR__ . '/../../includes/auth.php';
$u = require_user_type('customer');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
start_secure_session(); require_csrf();
$in = json_input();
$orderId = (int)($in['order_id'] ?? 0);
$rating = (int)($in['rating'] ?? 0);
$title = clean_str($in['title'] ?? '', 150);
$comment = clean_str($in['comment'] ?? '', 1200);
$errors = [];
if ($orderId < 1) $errors['order_id'] = 'Choose an order to review.';
if ($rating < 1 || $rating > 5) $errors['rating'] = 'Choose a rating from 1 to 5 stars.';
if ($title === '' && $comment === '') $errors['comment'] = 'Add a short note about your experience.';
if ($errors) json_error('Please check your review.', 422, ['fields' => $errors]);

$pdo = db(); $pdo->beginTransaction();
try {
    // Lock the owned order so two simultaneous submissions cannot create duplicate reviews.
    $orderQ = $pdo->prepare('SELECT id,restaurant_id,status FROM orders WHERE id=? AND customer_user_id=? FOR UPDATE');
    $orderQ->execute([$orderId, (int)$u['id']]); $order = $orderQ->fetch();
    if (!$order) { $pdo->rollBack(); json_error('That order could not be found in your account.', 404); }
    if (!in_array($order['status'], ['completed','delivered'], true)) {
        $pdo->rollBack(); json_error('You can review an order after it has been completed or delivered.', 409);
    }
    $reviewQ = $pdo->prepare('SELECT id FROM reviews WHERE order_id=? AND customer_user_id=? ORDER BY id LIMIT 1');
    $reviewQ->execute([$orderId, (int)$u['id']]); $reviewId = $reviewQ->fetchColumn();
    if ($reviewId) {
        $pdo->rollBack(); json_error('A review has already been submitted for this order.', 409);
    }
    $pdo->prepare('INSERT INTO reviews (restaurant_id,customer_user_id,order_id,rating,title,comment,is_approved) VALUES (?,?,?,?,?,?,1)')
        ->execute([(int)$order['restaurant_id'], (int)$u['id'], $orderId, $rating, $title ?: null, $comment ?: null]);
    $reviewId = (int)$pdo->lastInsertId(); $action = 'created';
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
log_activity('review.' . $action, (int)$u['id'], (int)$order['restaurant_id'], 'review', (int)$reviewId);
json_out(['ok' => true, 'message' => 'Your review has been saved.', 'review_id' => (int)$reviewId]);
