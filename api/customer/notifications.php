<?php
require_once __DIR__ . '/../../includes/auth.php';
$u = require_user_type('customer');
$userId = (int)$u['id'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['count_only'] ?? '') === '1') {
        $q = $pdo->prepare('SELECT COUNT(*) FROM customer_notifications WHERE user_id=? AND read_at IS NULL');
        $q->execute([$userId]);
        json_out(['ok' => true, 'unread_count' => (int)$q->fetchColumn()]);
    }
    $q = $pdo->prepare('SELECT n.id,n.order_id,n.status,n.title,n.message,n.read_at,n.created_at,o.order_number,r.name AS restaurant_name
        FROM customer_notifications n
        JOIN orders o ON o.id=n.order_id AND o.customer_user_id=n.user_id
        JOIN restaurants r ON r.id=o.restaurant_id
        WHERE n.user_id=? ORDER BY n.created_at DESC,n.id DESC LIMIT 100');
    $q->execute([$userId]);
    $rows = $q->fetchAll();
    $count = $pdo->prepare('SELECT COUNT(*) FROM customer_notifications WHERE user_id=? AND read_at IS NULL');
    $count->execute([$userId]);
    json_out(['ok' => true, 'unread_count' => (int)$count->fetchColumn(), 'notifications' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
start_secure_session(); require_csrf();
$in = json_input();
if (($in['action'] ?? '') === 'read_all') {
    $pdo->prepare('UPDATE customer_notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([$userId]);
} elseif (($in['action'] ?? '') === 'read') {
    $id = (int)($in['id'] ?? 0);
    if ($id < 1) json_error('Choose an update to mark as read.', 422);
    $pdo->prepare('UPDATE customer_notifications SET read_at=NOW() WHERE id=? AND user_id=? AND read_at IS NULL')->execute([$id,$userId]);
} else {
    json_error('Choose a valid notification action.', 422);
}
$q = $pdo->prepare('SELECT COUNT(*) FROM customer_notifications WHERE user_id=? AND read_at IS NULL');
$q->execute([$userId]);
json_out(['ok' => true, 'unread_count' => (int)$q->fetchColumn()]);
