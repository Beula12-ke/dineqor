<?php
// POST {ingredient_id, type: waste|adjustment, direction: subtract|add, quantity, notes?}
// 'waste' always subtracts. 'adjustment' can go either way (e.g. correcting a stock-take miscount).
require_once __DIR__ . '/../../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session(); require_csrf();
$u = require_permission('manage_inventory');
$rid = tenant_id($u);
$pdo = db();

$in = json_input();
$ingId = (int)($in['ingredient_id'] ?? 0);
$type = (string)($in['type'] ?? '');
$dir = (string)($in['direction'] ?? 'subtract');
$qty = $in['quantity'] ?? '';
$notes = clean_str($in['notes'] ?? '', 255) ?: null;

if (!in_array($type, ['waste', 'adjustment'], true)) json_error('Invalid request.', 422);
if ($type === 'waste') $dir = 'subtract';
if (!in_array($dir, ['add', 'subtract'], true)) json_error('Invalid request.', 422);
if (!is_numeric($qty) || $qty <= 0) json_error('Enter a quantity greater than 0.', 422, ['fields' => ['quantity' => 'Enter a quantity greater than 0.']]);

try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT id, name, current_stock FROM ingredients WHERE id=? AND restaurant_id=? FOR UPDATE');
    $st->execute([$ingId, $rid]);
    $ing = $st->fetch();
    if (!$ing) { $pdo->rollBack(); json_error('Ingredient not found.', 404); }

    $delta = $dir === 'add' ? (float)$qty : -(float)$qty;
    $pdo->prepare('UPDATE ingredients SET current_stock = GREATEST(0, current_stock + ?) WHERE id=? AND restaurant_id=?')
        ->execute([$delta, $ingId, $rid]);
    $pdo->prepare('INSERT INTO stock_movements (restaurant_id, ingredient_id, quantity, type, notes, user_id) VALUES (?,?,?,?,?,?)')
        ->execute([$rid, $ingId, $delta, $type, $notes, (int)$u['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('stock-adjust: ' . $e->getMessage());
    json_error('Could not update stock. Please try again.', 500);
}
log_activity('ingredient.' . $type, (int)$u['id'], $rid, 'ingredient', $ingId, $ing['name'] . ' ' . ($delta >= 0 ? '+' : '') . $delta);
json_out(['ok' => true]);
