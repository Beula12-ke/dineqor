<?php
// ONE template for EVERY restaurant. The page itself is a thin shell; data comes from api/public/restaurant.php
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/tenant.php';

$slug = clean_str($_GET['slug'] ?? '', 80);
$r = resolve_public_restaurant($slug ?: null, $slug ? null : ($_SERVER['HTTP_HOST'] ?? null));
if (!$r) {
    http_response_code(404);
    page_head('Not found'); nav_bar(current_user());
    echo '<main class="wrap"><div class="empty"><b>Restaurant not found</b><a href="' . e(url()) . '">Browse all restaurants</a></div></main>';
    page_foot(); exit;
}

// Restaurant QR scan tracking: /restaurant/{slug}?qr={token}
$qrTok = clean_str($_GET['qr'] ?? '', 60);
if ($qrTok !== '') {
    $qs = db()->prepare("SELECT id FROM qr_codes WHERE token=? AND restaurant_id=? AND qr_type='restaurant' AND is_active=1");
    $qs->execute([$qrTok, (int)$r['id']]);
    if ($qid = $qs->fetchColumn()) {
        db()->prepare('UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id=?')->execute([$qid]);
        db()->prepare('INSERT INTO qr_scans (qr_id, restaurant_id, ip_address, user_agent, referer) VALUES (?,?,?,?,?)')
           ->execute([$qid, (int)$r['id'], client_ip_bin(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500)]);
    }
}

// Brand colours are injected server-side too, so the first paint already matches the restaurant (no flash)
$hex = fn($c, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c) ? $c : $d;
$css = ':root{--brand:' . $hex($r['primary_color'], '#e63946') . ';--ink:' . $hex($r['secondary_color'], '#1d3557') . '}';

page_head($r['name'], $css);
$viewer = current_user();
nav_bar($viewer);
if ($viewer && $viewer['user_type'] === 'restaurant_owner' && (int)($viewer['restaurant_id'] ?? 0) === (int)$r['id']) {
    echo '<div class="store-owner-preview"><span><b>Storefront preview</b><small>Only you can see this owner shortcut.</small></span><a href="' . e(url('restaurant/dashboard.php')) . '">← Back to owner dashboard</a></div>';
}
echo '<div id="store" data-slug="' . e($r['slug']) . '"><noscript><main class="wrap"><div class="empty">Please enable JavaScript to view the menu.</div></main></noscript></div>';
page_foot('restaurant.js', 'cart.js', 'reservations.js', 'customer.js');
