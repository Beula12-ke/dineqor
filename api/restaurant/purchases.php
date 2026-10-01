<?php
// GET: recent purchases (stock-in history).
// POST {ingredient_id, quantity, unit_cost, supplier_id?, notes?} - single-line restock, kept in the `purchases`
// table so a full multi-item purchase order can be layered on later without a schema change.
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('manage_inventory');
$rid = tenant_id($u);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session(); require_csrf();
    $in = json_input();

    $ingId = (int)($in['ingredient_id'] ?? 0);
    $qty = $in['quantity'] ?? '';
    $cost = $in['unit_cost'] ?? '';
    $supplierId = (int)($in['supplier_id'] ?? 0) ?: null;
    $notes = clean_str($in['notes'] ?? '', 255) ?: null;

    $errors = [];
    if (!is_numeric($qty) || $qty <= 0) $errors['quantity'] = 'Enter a quantity greater than 0.';
    if (!is_numeric($cost) || $cost < 0) $errors['unit_cost'] = 'Enter a valid cost.';
    if ($errors) json_error('Please fix the highlighted fields.', 422, ['fields' => $errors]);

    $ing = $pdo->prepare('SELECT id, name FROM ingredients WHERE id=? AND restaurant_id=?');
    $ing->execute([$ingId, $rid]);
    $ingredient = $ing->fetch();
    if (!$ingredient) json_error('Ingredient not found.', 404);

    if ($supplierId) {
        $s = $pdo->prepare('SELECT 1 FROM suppliers WHERE id=? AND restaurant_id=? AND is_active=1');
        $s->execute([$supplierId, $rid]);
        if (!$s->fetch()) json_error('Supplier not found.', 422);
    }

    $lineTotal = round((float)$qty * (float)$cost, 2);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO purchases (restaurant_id, supplier_id, total_amount, status, purchased_at, notes) VALUES (?,?,?, 'received', CURDATE(), ?)")
            ->execute([$rid, $supplierId, $lineTotal, $notes]);
        $purchaseId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO purchase_items (purchase_id, ingredient_id, quantity, unit_cost, line_total) VALUES (?,?,?,?,?)')
            ->execute([$purchaseId, $ingId, $qty, $cost, $lineTotal]);
        $pdo->prepare('UPDATE ingredients SET current_stock = current_stock + ?, cost_per_unit = ? WHERE id=? AND restaurant_id=?')
            ->execute([$qty, $cost, $ingId, $rid]);
        $pdo->prepare("INSERT INTO stock_movements (restaurant_id, ingredient_id, quantity, type, reference_id, notes, user_id) VALUES (?,?,?, 'purchase', ?, ?, ?)")
            ->execute([$rid, $ingId, $qty, $purchaseId, $notes ?: ('Restock: ' . $ingredient['name']), (int)$u['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('purchase: ' . $e->getMessage());
        json_error('Could not record the restock. Please try again.', 500);
    }
    log_activity('ingredient.restock', (int)$u['id'], $rid, 'ingredient', $ingId, $ingredient['name'] . ' +' . $qty);
    json_out(['ok' => true, 'purchase_id' => $purchaseId], 201);
}

$st = $pdo->prepare("SELECT p.id, p.total_amount, p.purchased_at, p.notes, p.created_at, s.name AS supplier_name,
        GROUP_CONCAT(CONCAT(i.name, ' +', pi.quantity, ' ', i.unit) SEPARATOR ', ') AS items
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    JOIN purchase_items pi ON pi.purchase_id = p.id
    JOIN ingredients i ON i.id = pi.ingredient_id
    WHERE p.restaurant_id = ? GROUP BY p.id ORDER BY p.created_at DESC LIMIT 50");
$st->execute([$rid]);
json_out(['ok' => true, 'csrf' => csrf_token(), 'purchases' => $st->fetchAll()]);
