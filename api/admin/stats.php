<?php
// Platform dashboard data (admin only): counts + latest applications
require_once __DIR__ . '/../../includes/auth.php';
require_user_type('platform_admin');

$n = fn(string $sql): int => (int)db()->query($sql)->fetchColumn();
$apps = db()->query("SELECT r.id, r.name, r.slug, r.status, r.cuisine, r.city, r.created_at, u.full_name AS owner, u.email
                     FROM restaurants r LEFT JOIN users u ON u.id = r.owner_user_id
                     WHERE r.deleted_at IS NULL AND r.status IN ('pending','active','suspended')
                     ORDER BY (r.status = 'pending') DESC, r.created_at DESC LIMIT 25")->fetchAll();

json_out(['ok' => true,
  'stats' => [
    'total'     => $n("SELECT COUNT(*) FROM restaurants WHERE deleted_at IS NULL"),
    'active'    => $n("SELECT COUNT(*) FROM restaurants WHERE status='active' AND deleted_at IS NULL"),
    'pending'   => $n("SELECT COUNT(*) FROM restaurants WHERE status='pending' AND deleted_at IS NULL"),
    'suspended' => $n("SELECT COUNT(*) FROM restaurants WHERE status='suspended' AND deleted_at IS NULL"),
    'customers' => $n("SELECT COUNT(*) FROM users WHERE user_type='customer' AND deleted_at IS NULL"),
    'orders'    => $n("SELECT COUNT(*) FROM orders"),
  ],
  'restaurants' => $apps,
]);
