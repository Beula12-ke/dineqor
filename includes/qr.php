<?php
require_once __DIR__ . '/security.php';

// Make sure the restaurant has its storefront QR (never needs regenerating when the menu changes)
function ensure_restaurant_qr(int $rid, string $slug): void {
    $st = db()->prepare("SELECT 1 FROM qr_codes WHERE restaurant_id=? AND qr_type='restaurant' LIMIT 1");
    $st->execute([$rid]);
    if ($st->fetch()) return;
    $token = new_qr_token();
    db()->prepare("INSERT INTO qr_codes (restaurant_id, table_id, qr_type, token, target_url) VALUES (?,NULL,'restaurant',?,?)")
        ->execute([$rid, $token, 'restaurant/' . $slug . '?qr=' . $token]);
}

function create_table_with_qr(int $rid, string $slug, int $number, ?string $label = null, int $capacity = 4, ?string $section = null): int {
    db()->prepare('INSERT INTO restaurant_tables (restaurant_id, table_number, label, capacity, section) VALUES (?,?,?,?,?)')
        ->execute([$rid, $number, $label ?: ('Table ' . $number), $capacity, $section]);
    $tid = (int)db()->lastInsertId();
    $token = new_qr_token();
    db()->prepare("INSERT INTO qr_codes (restaurant_id, table_id, qr_type, token, target_url) VALUES (?,?, 'table', ?, ?)")
        ->execute([$rid, $tid, $token, 'order/' . $slug . '/table/' . $token]);
    return $tid;
}
