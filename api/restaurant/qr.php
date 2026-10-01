<?php
// POST {action: regenerate|enable|disable, id}  -- id is a qr_codes.id that must belong to THIS restaurant
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session(); require_csrf();
$u = require_permission('manage_qr');
$rid = tenant_id($u);
$in = json_input();
$id = (int)($in['id'] ?? 0);
$action = (string)($in['action'] ?? '');

$st = db()->prepare('SELECT q.id, q.qr_type, r.slug FROM qr_codes q JOIN restaurants r ON r.id = q.restaurant_id WHERE q.id=? AND q.restaurant_id=?');
$st->execute([$id, $rid]);
$q = $st->fetch();
if (!$q) json_error('QR code not found.', 404);

if ($action === 'regenerate') {
    $token = new_qr_token();
    $url = $q['qr_type'] === 'restaurant' ? 'restaurant/' . $q['slug'] . '?qr=' . $token : 'order/' . $q['slug'] . '/table/' . $token;
    db()->prepare('UPDATE qr_codes SET token=?, target_url=?, is_active=1 WHERE id=? AND restaurant_id=?')->execute([$token, $url, $id, $rid]);
} elseif ($action === 'enable' || $action === 'disable') {
    db()->prepare('UPDATE qr_codes SET is_active=? WHERE id=? AND restaurant_id=?')->execute([$action === 'enable' ? 1 : 0, $id, $rid]);
} else json_error('Invalid request.', 422);

log_activity('qr.' . $action, (int)$u['id'], $rid, 'qr', $id);
json_out(['ok' => true]);
