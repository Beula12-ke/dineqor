<?php
// GET: list ingredients (+ low-stock flag). POST {action: create|update|delete, id?, name, unit, min_stock, cost_per_unit, current_stock?}
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('manage_inventory');
$rid = tenant_id($u);
$pdo = db();

const UNITS = ['g', 'kg', 'ml', 'l', 'pcs'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session(); require_csrf();
    $in = json_input();
    $action = (string)($in['action'] ?? '');
    $id = (int)($in['id'] ?? 0);

    if ($action === 'delete') {
        $used = $pdo->prepare('SELECT 1 FROM recipes WHERE ingredient_id = ? LIMIT 1');
        $used->execute([$id]);
        if ($used->fetch()) json_error('This ingredient is used in a recipe. Remove it from the recipe first.', 409);
        $st = $pdo->prepare('DELETE FROM ingredients WHERE id = ? AND restaurant_id = ?');
        $st->execute([$id, $rid]);
        if (!$st->rowCount()) json_error('Ingredient not found.', 404);
        log_activity('ingredient.delete', (int)$u['id'], $rid, 'ingredient', $id);
        json_out(['ok' => true]);
    }

    if (!in_array($action, ['create', 'update'], true)) json_error('Invalid request.', 422);
    $name = clean_str($in['name'] ?? '', 150);
    $unit = (string)($in['unit'] ?? 'g');
    $min  = $in['min_stock'] ?? 0;
    $cost = $in['cost_per_unit'] ?? 0;

    $errors = [];
    if ($name === '') $errors['name'] = 'Enter a name.';
    if (!in_array($unit, UNITS, true)) $errors['unit'] = 'Choose a unit.';
    if (!is_numeric($min) || $min < 0) $errors['min_stock'] = 'Enter a valid amount.';
    if (!is_numeric($cost) || $cost < 0) $errors['cost_per_unit'] = 'Enter a valid cost.';
    if ($errors) json_error('Please fix the highlighted fields.', 422, ['fields' => $errors]);

    if ($action === 'create') {
        $stock = $in['current_stock'] ?? 0;
        if (!is_numeric($stock) || $stock < 0) json_error('Enter a valid starting stock.', 422, ['fields' => ['current_stock' => 'Enter a valid amount.']]);
        $pdo->prepare('INSERT INTO ingredients (restaurant_id, name, unit, current_stock, min_stock, cost_per_unit) VALUES (?,?,?,?,?,?)')
            ->execute([$rid, $name, $unit, $stock, $min, $cost]);
        $id = (int)$pdo->lastInsertId();
        if ((float)$stock > 0) {
            $pdo->prepare("INSERT INTO stock_movements (restaurant_id, ingredient_id, quantity, type, notes, user_id) VALUES (?,?,?, 'adjustment', 'Starting stock', ?)")
                ->execute([$rid, $id, $stock, (int)$u['id']]);
        }
    } else {
        $st = $pdo->prepare('UPDATE ingredients SET name=?, unit=?, min_stock=?, cost_per_unit=? WHERE id=? AND restaurant_id=?');
        $st->execute([$name, $unit, $min, $cost, $id, $rid]);
        $chk = $pdo->prepare('SELECT 1 FROM ingredients WHERE id=? AND restaurant_id=?');
        $chk->execute([$id, $rid]);
        if (!$chk->fetch()) json_error('Ingredient not found.', 404);
    }
    log_activity('ingredient.' . $action, (int)$u['id'], $rid, 'ingredient', $id, $name);
    json_out(['ok' => true, 'id' => $id]);
}

$st = $pdo->prepare('SELECT id, name, unit, current_stock, min_stock, cost_per_unit FROM ingredients WHERE restaurant_id=? ORDER BY name');
$st->execute([$rid]);
$rows = array_map(function ($r) {
    $r['id'] = (int)$r['id']; $r['current_stock'] = (float)$r['current_stock'];
    $r['min_stock'] = (float)$r['min_stock']; $r['cost_per_unit'] = (float)$r['cost_per_unit'];
    $r['low'] = $r['current_stock'] <= $r['min_stock'];
    return $r;
}, $st->fetchAll());

$sup = $pdo->prepare('SELECT id, name FROM suppliers WHERE restaurant_id=? AND is_active=1 ORDER BY name');
$sup->execute([$rid]);

json_out(['ok' => true, 'csrf' => csrf_token(), 'units' => UNITS, 'ingredients' => $rows, 'suppliers' => $sup->fetchAll(),
          'low_count' => count(array_filter($rows, fn($r) => $r['low']))]);
