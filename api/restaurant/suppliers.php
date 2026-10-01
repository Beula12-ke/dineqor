<?php
// GET: list suppliers. POST {action: create|update|delete, id?, name, phone?, email?, address?, notes?}
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('manage_inventory');
$rid = tenant_id($u);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session(); require_csrf();
    $in = json_input();
    $action = (string)($in['action'] ?? '');
    $id = (int)($in['id'] ?? 0);

    if ($action === 'delete') {
        $st = $pdo->prepare('UPDATE suppliers SET is_active=0 WHERE id=? AND restaurant_id=?');
        $st->execute([$id, $rid]);
        if (!$st->rowCount()) json_error('Supplier not found.', 404);
        log_activity('supplier.delete', (int)$u['id'], $rid, 'supplier', $id);
        json_out(['ok' => true]);
    }
    if (!in_array($action, ['create', 'update'], true)) json_error('Invalid request.', 422);

    $name = clean_str($in['name'] ?? '', 150);
    if ($name === '') json_error('Enter a supplier name.', 422, ['fields' => ['name' => 'Enter a name.']]);
    $phone = clean_str($in['phone'] ?? '', 30) ?: null;
    $email = clean_str($in['email'] ?? '', 150) ?: null;
    $address = clean_str($in['address'] ?? '', 500) ?: null;
    $notes = clean_str($in['notes'] ?? '', 500) ?: null;
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Enter a valid email.', 422, ['fields' => ['email' => 'Enter a valid email.']]);

    if ($action === 'create') {
        $pdo->prepare('INSERT INTO suppliers (restaurant_id, name, phone, email, address, notes) VALUES (?,?,?,?,?,?)')
            ->execute([$rid, $name, $phone, $email, $address, $notes]);
        $id = (int)$pdo->lastInsertId();
    } else {
        $st = $pdo->prepare('UPDATE suppliers SET name=?, phone=?, email=?, address=?, notes=?, is_active=1 WHERE id=? AND restaurant_id=?');
        $st->execute([$name, $phone, $email, $address, $notes, $id, $rid]);
        $chk = $pdo->prepare('SELECT 1 FROM suppliers WHERE id=? AND restaurant_id=?');
        $chk->execute([$id, $rid]);
        if (!$chk->fetch()) json_error('Supplier not found.', 404);
    }
    log_activity('supplier.' . $action, (int)$u['id'], $rid, 'supplier', $id, $name);
    json_out(['ok' => true, 'id' => $id]);
}

$st = $pdo->prepare('SELECT id, name, phone, email, address, notes FROM suppliers WHERE restaurant_id=? AND is_active=1 ORDER BY name');
$st->execute([$rid]);
json_out(['ok' => true, 'csrf' => csrf_token(), 'suppliers' => $st->fetchAll()]);
