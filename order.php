<?php
// Table QR landing: /order/{slug}/table/{token}. Restaurant + table are resolved from the secret token on the SERVER.
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/tenant.php';

$slug = clean_str($_GET['slug'] ?? '', 80);
$token = clean_str($_GET['token'] ?? '', 60);
$st = db()->prepare("SELECT q.id AS qr_id, q.is_active, q.table_id, r.id AS rid, r.slug, r.name, r.primary_color, r.secondary_color, t.label, t.table_number
    FROM qr_codes q JOIN restaurants r ON r.id = q.restaurant_id JOIN restaurant_tables t ON t.id = q.table_id
    WHERE q.token = ? AND q.qr_type = 'table' AND r.slug = ? AND r.status = 'active' AND r.deleted_at IS NULL");
$st->execute([$token, $slug]);
$q = $st->fetch();

if (!$q || !$q['is_active']) {
    http_response_code($q ? 410 : 404);
    page_head('QR code unavailable'); nav_bar(current_user());
    echo '<main class="wrap"><div class="empty"><b>' . ($q ? 'This QR code is not active' : 'QR code not found') . '</b>Please ask the restaurant staff for help.</div></main>';
    page_foot(); exit;
}

// Count the scan
db()->prepare('UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id = ?')->execute([$q['qr_id']]);
db()->prepare('INSERT INTO qr_scans (qr_id, restaurant_id, table_id, ip_address, user_agent, referer) VALUES (?,?,?,?,?,?)')
   ->execute([$q['qr_id'], $q['rid'], $q['table_id'], client_ip_bin(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500)]);

// Remember the table for checkout (used by the ordering milestone)
start_secure_session();
$_SESSION['table_ctx'] = ['restaurant_id' => (int)$q['rid'], 'table_id' => (int)$q['table_id'], 'token' => $token];

$hex = fn($c, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c) ? $c : $d;
page_head($q['name'] . ' · ' . $q['label'], ':root{--brand:' . $hex($q['primary_color'], '#e63946') . ';--ink:' . $hex($q['secondary_color'], '#1d3557') . '}');
echo '<div class="tablebar"><div class="wrap"><span>' . e($q['name']) . '</span><b>' . e(strtoupper($q['label'] ?: 'Table ' . $q['table_number'])) . '</b></div></div>';
echo '<div id="store" data-slug="' . e($q['slug']) . '"></div>';
page_foot('restaurant.js', 'cart.js');
