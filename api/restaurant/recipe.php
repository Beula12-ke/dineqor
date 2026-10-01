<?php
// GET ?product_id=.. -> that product's recipe lines + the full ingredient list (for the picker).
// POST {product_id, lines:[{ingredient_id, quantity}]} -> replaces the product's recipe entirely.
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('manage_inventory');
$rid = tenant_id($u);
$pdo = db();

function owned_product(PDO $pdo, int $rid, int $pid): ?array {
    $st = $pdo->prepare('SELECT id, name FROM products WHERE id=? AND restaurant_id=? AND deleted_at IS NULL');
    $st->execute([$pid, $rid]);
    return $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session(); require_csrf();
    $in = json_input();
    $pid = (int)($in['product_id'] ?? 0);
    $product = owned_product($pdo, $rid, $pid);
    if (!$product) json_error('Menu item not found.', 404);

    $lines = (array)($in['lines'] ?? []);
    if (count($lines) > 40) json_error('Too many ingredients on one recipe.', 422);
    $clean = []; $seen = [];
    foreach ($lines as $l) {
        $iid = (int)($l['ingredient_id'] ?? 0);
        $qty = $l['quantity'] ?? '';
        if (!$iid || !is_numeric($qty) || $qty <= 0) continue;
        if (isset($seen[$iid])) continue;
        $seen[$iid] = true;
        $clean[] = [$iid, $qty];
    }

    if ($clean) {
        $ids = array_column($clean, 0);
        $in2 = implode(',', array_fill(0, count($ids), '?'));
        $chk = $pdo->prepare("SELECT COUNT(*) FROM ingredients WHERE restaurant_id=? AND id IN ($in2)");
        $chk->execute(array_merge([$rid], $ids));
        if ((int)$chk->fetchColumn() !== count(array_unique($ids))) json_error('One of those ingredients was not found.', 422);
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM recipes WHERE product_id=?')->execute([$pid]);
        $ins = $pdo->prepare('INSERT INTO recipes (product_id, ingredient_id, quantity) VALUES (?,?,?)');
        foreach ($clean as [$iid, $qty]) $ins->execute([$pid, $iid, $qty]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('recipe save: ' . $e->getMessage());
        json_error('Could not save the recipe. Please try again.', 500);
    }
    log_activity('recipe.save', (int)$u['id'], $rid, 'product', $pid, $product['name'], ['ingredients' => count($clean)]);
    json_out(['ok' => true]);
}

$pid = (int)($_GET['product_id'] ?? 0);
$ingSt = $pdo->prepare('SELECT id, name, unit FROM ingredients WHERE restaurant_id=? ORDER BY name');
$ingSt->execute([$rid]);
$ingredients = $ingSt->fetchAll();

$lines = [];
if ($pid) {
    $product = owned_product($pdo, $rid, $pid);
    if (!$product) json_error('Menu item not found.', 404);
    $l = $pdo->prepare('SELECT ingredient_id, quantity FROM recipes WHERE product_id=?');
    $l->execute([$pid]);
    $lines = array_map(fn($r) => ['ingredient_id' => (int)$r['ingredient_id'], 'quantity' => (float)$r['quantity']], $l->fetchAll());
}

$pSt = $pdo->prepare('SELECT id, name FROM products WHERE restaurant_id=? AND deleted_at IS NULL ORDER BY name');
$pSt->execute([$rid]);

json_out(['ok' => true, 'csrf' => csrf_token(), 'ingredients' => $ingredients, 'products' => $pSt->fetchAll(), 'lines' => $lines]);
