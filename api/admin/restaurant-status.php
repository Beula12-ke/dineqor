<?php
// Approve / suspend / reactivate a restaurant (admin only). POST {id, action: approve|suspend|reactivate}
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session();
require_csrf();
$admin = require_user_type('platform_admin');

$in = json_input();
$id = (int)($in['id'] ?? 0);
$action = (string)($in['action'] ?? '');
$map = [                                   // action => [required current status, new status]
    'approve'    => ['pending',   'active'],
    'reject'     => ['pending',   'rejected'],
    'suspend'    => ['active',    'suspended'],
    'reactivate' => ['suspended', 'active'],
];
if ($id < 1 || !isset($map[$action])) json_error('Invalid request.', 422);
[$from, $to] = $map[$action];

$st = db()->prepare("UPDATE restaurants SET status = ?,
                       approved_at = IF(? = 'active' AND approved_at IS NULL, NOW(), approved_at),
                       approved_by = IF(? = 'active' AND approved_by IS NULL, ?, approved_by)
                     WHERE id = ? AND status = ? AND deleted_at IS NULL");
$st->execute([$to, $to, $to, (int)$admin['id'], $id, $from]);
if (!$st->rowCount()) json_error('That restaurant is no longer ' . $from . '. Refresh the list.', 409);

log_activity('restaurant.' . $action, (int)$admin['id'], $id, 'restaurant', $id, "Restaurant $action");
json_out(['ok' => true, 'status' => $to]);
