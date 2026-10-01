<?php
// Ingredient stock deduction from recipes when products are sold.
// Call this from INSIDE the caller's own transaction, after the order/order_items rows exist.
require_once __DIR__ . '/security.php';

// $sold = [product_id => qty]. For every product with a recipe, deducts qty*recipe.quantity from the
// matching ingredient's current_stock (never below 0) and logs one stock_movements row per ingredient.
function deduct_ingredients_for_sale(int $rid, array $sold, ?int $orderId, ?int $userId): void {
    if (!$sold) return;
    $pdo = db();
    $ids = array_keys($sold);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT product_id, ingredient_id, quantity FROM recipes WHERE product_id IN ($in)");
    $st->execute($ids);

    $need = [];
    foreach ($st->fetchAll() as $r) {
        $pid = (int)$r['product_id'];
        if (!isset($sold[$pid])) continue;
        $qty = (float)$r['quantity'] * (int)$sold[$pid];
        $iid = (int)$r['ingredient_id'];
        $need[$iid] = ($need[$iid] ?? 0) + $qty;
    }
    if (!$need) return;

    $lock = $pdo->prepare('SELECT id FROM ingredients WHERE id = ? AND restaurant_id = ? FOR UPDATE');
    $upd  = $pdo->prepare('UPDATE ingredients SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ? AND restaurant_id = ?');
    $mv   = $pdo->prepare("INSERT INTO stock_movements (restaurant_id, ingredient_id, quantity, type, reference_id, notes, user_id)
                           VALUES (?,?,?, 'sale', ?, 'Order sale', ?)");
    foreach ($need as $ingId => $qty) {
        if ($qty <= 0) continue;
        $lock->execute([$ingId, $rid]);
        if (!$lock->fetch()) continue;                 // ingredient deleted, or belongs to another restaurant - skip
        $upd->execute([$qty, $ingId, $rid]);
        $mv->execute([$rid, $ingId, -$qty, $orderId, $userId]);
    }
}

// Count of ingredients at/under their minimum for this restaurant - used by dashboards.
function low_stock_count(int $rid): int {
    $st = db()->prepare('SELECT COUNT(*) FROM ingredients WHERE restaurant_id = ? AND current_stock <= min_stock');
    $st->execute([$rid]);
    return (int)$st->fetchColumn();
}
