<?php
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('manage_inventory');
$rid = tenant_id($u); $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $today=date('Y-m-d'); $defaultFrom=date('Y-m-01');
    $from=(string)($_GET['from'] ?? $defaultFrom); $to=(string)($_GET['to'] ?? $today);
    $fromObj=DateTime::createFromFormat('!Y-m-d',$from); $toObj=DateTime::createFromFormat('!Y-m-d',$to);
    if (!$fromObj || $fromObj->format('Y-m-d')!==$from || !$toObj || $toObj->format('Y-m-d')!==$to || $from>$to || $to>$today || (strtotime($to)-strtotime($from))>366*86400) {
        json_error('Choose a valid date range within the last year.',422);
    }
    $ingredientsQ = $pdo->prepare('SELECT id,name,unit,current_stock,min_stock,cost_per_unit FROM ingredients WHERE restaurant_id=? ORDER BY (current_stock<=min_stock) DESC,name');
    $ingredientsQ->execute([$rid]); $ingredients = $ingredientsQ->fetchAll();
    $supplierQ = $pdo->prepare('SELECT id,name,phone,email FROM suppliers WHERE restaurant_id=? AND is_active=1 ORDER BY name');
    $supplierQ->execute([$rid]); $suppliers = $supplierQ->fetchAll();
    $movementQ = $pdo->prepare('SELECT sm.id,sm.ingredient_id,i.name AS ingredient_name,i.unit,sm.quantity,sm.type,sm.notes,sm.created_at,u.full_name AS staff_name
        FROM stock_movements sm JOIN ingredients i ON i.id=sm.ingredient_id LEFT JOIN users u ON u.id=sm.user_id
        WHERE sm.restaurant_id=? ORDER BY sm.created_at DESC,sm.id DESC LIMIT 40');
    $movementQ->execute([$rid]); $movements = $movementQ->fetchAll();
    $purchaseQ = $pdo->prepare('SELECT p.id,p.total_amount,p.status,p.purchased_at,p.created_at,p.notes,s.name AS supplier_name,COUNT(pi.id) AS item_count
        FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id LEFT JOIN purchase_items pi ON pi.purchase_id=p.id
        WHERE p.restaurant_id=? GROUP BY p.id,s.name ORDER BY p.created_at DESC LIMIT 20');
    $purchaseQ->execute([$rid]); $purchases = $purchaseQ->fetchAll();
    $metricsQ = $pdo->prepare('SELECT COUNT(*) AS ingredients,COALESCE(SUM(current_stock<=min_stock),0) AS low_stock,COALESCE(SUM(current_stock*cost_per_unit),0) AS stock_value FROM ingredients WHERE restaurant_id=?');
    $metricsQ->execute([$rid]); $metrics = $metricsQ->fetch();
    $productsQ=$pdo->prepare('SELECT id,name FROM products WHERE restaurant_id=? AND deleted_at IS NULL ORDER BY name');
    $productsQ->execute([$rid]); $products=$productsQ->fetchAll();
    $recipesQ=$pdo->prepare('SELECT r.product_id,r.ingredient_id,r.quantity,i.name AS ingredient_name,i.unit,p.name AS product_name
        FROM recipes r JOIN products p ON p.id=r.product_id AND p.restaurant_id=? JOIN ingredients i ON i.id=r.ingredient_id AND i.restaurant_id=?
        ORDER BY p.name,i.name');
    $recipesQ->execute([$rid,$rid]); $recipes=$recipesQ->fetchAll();
    $reportQ=$pdo->prepare("SELECT i.id,i.name,i.unit,i.current_stock,i.min_stock,i.cost_per_unit,
        COALESCE(SUM(CASE WHEN sm.type='purchase' THEN sm.quantity ELSE 0 END),0) AS purchased,
        COALESCE(SUM(CASE WHEN sm.type='sale' THEN -sm.quantity ELSE 0 END),0) AS used,
        COALESCE(SUM(CASE WHEN sm.type='waste' THEN -sm.quantity ELSE 0 END),0) AS wasted,
        COALESCE(SUM(CASE WHEN sm.type='adjustment' THEN sm.quantity ELSE 0 END),0) AS adjusted,
        COALESCE(SUM(CASE WHEN sm.type='return' THEN sm.quantity ELSE 0 END),0) AS returned
        FROM ingredients i LEFT JOIN stock_movements sm ON sm.ingredient_id=i.id AND sm.restaurant_id=i.restaurant_id
        AND sm.created_at>=? AND sm.created_at<DATE_ADD(?,INTERVAL 1 DAY)
        WHERE i.restaurant_id=? GROUP BY i.id ORDER BY (used+wasted) DESC,i.name");
    $reportQ->execute([$from,$to,$rid]); $inventoryReport=$reportQ->fetchAll();
    json_out(['ok'=>true,'csrf'=>csrf_token(),'ingredients'=>$ingredients,'suppliers'=>$suppliers,'movements'=>$movements,'purchases'=>$purchases,'products'=>$products,'recipes'=>$recipes,
        'report'=>['from'=>$from,'to'=>$to,'rows'=>$inventoryReport],
        'metrics'=>['ingredients'=>(int)$metrics['ingredients'],'low_stock'=>(int)$metrics['low_stock'],'stock_value'=>(float)$metrics['stock_value']]]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
start_secure_session(); require_csrf();
$in = json_input(); $action = (string)($in['action'] ?? '');

if ($action === 'save_recipe' || $action === 'clear_recipe') {
    $productId=(int)($in['product_id'] ?? 0);
    if ($productId<1) json_error('Choose a menu item.',422);
    $productQ=$pdo->prepare('SELECT id FROM products WHERE id=? AND restaurant_id=? AND deleted_at IS NULL');
    $productQ->execute([$productId,$rid]);
    if (!$productQ->fetchColumn()) json_error('Menu item not found.',404);
    if ($action==='clear_recipe') {
        $pdo->prepare('DELETE FROM recipes WHERE product_id=?')->execute([$productId]);
        log_activity('inventory.recipe_cleared',(int)$u['id'],$rid,'product',$productId);
        json_out(['ok'=>true,'message'=>'Recipe removed.']);
    }
    $items=$in['ingredients'] ?? [];
    if (!is_array($items) || !$items || count($items)>60) json_error('Add between 1 and 60 recipe ingredients.',422);
    $normalized=[]; $seen=[];
    foreach ($items as $item) {
        $ingredientId=(int)($item['ingredient_id'] ?? 0); $quantity=$item['quantity'] ?? '';
        if ($ingredientId<1 || !is_numeric($quantity) || (float)$quantity<=0 || (float)$quantity>999999999.999 || isset($seen[$ingredientId])) json_error('Check each recipe ingredient and its quantity. Use a unique ingredient with a positive quantity.',422);
        $seen[$ingredientId]=true; $normalized[$ingredientId]=round((float)$quantity,3);
        if ($normalized[$ingredientId]<=0) json_error('Recipe quantities must be at least 0.001.',422);
    }
    $ids=array_keys($normalized); $inSql=implode(',',array_fill(0,count($ids),'?'));
    $validQ=$pdo->prepare("SELECT id FROM ingredients WHERE restaurant_id=? AND id IN ($inSql)");
    $validQ->execute(array_merge([$rid],$ids));
    if (count($validQ->fetchAll())!==count($ids)) json_error('Choose ingredients from this restaurant.',422);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM recipes WHERE product_id=?')->execute([$productId]);
        $insert=$pdo->prepare('INSERT INTO recipes (product_id,ingredient_id,quantity) VALUES (?,?,?)');
        foreach ($normalized as $ingredientId=>$quantity) $insert->execute([$productId,$ingredientId,$quantity]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    log_activity('inventory.recipe_saved',(int)$u['id'],$rid,'product',$productId);
    json_out(['ok'=>true,'message'=>'Recipe saved. Ingredient stock will be used when this menu item is ordered.']);
}

if ($action === 'save_ingredient') {
    $id = (int)($in['id'] ?? 0); $name = clean_str($in['name'] ?? '', 150); $unit = clean_str($in['unit'] ?? '', 20);
    $min = $in['min_stock'] ?? ''; $cost = $in['cost_per_unit'] ?? ''; $initial = $in['initial_stock'] ?? 0;
    $units = ['g','kg','ml','l','pcs','portion','bottle','pack','each','unit'];
    $errors = [];
    if (mb_strlen($name) < 2) $errors['name'] = 'Enter an ingredient name.';
    if (!in_array($unit, $units, true)) $errors['unit'] = 'Choose a supported stock unit.';
    if (!is_numeric($min) || (float)$min < 0 || (float)$min > 1000000) $errors['min_stock'] = 'Enter a valid minimum quantity.';
    if (!is_numeric($cost) || (float)$cost < 0 || (float)$cost > 10000000) $errors['cost_per_unit'] = 'Enter a valid unit cost.';
    if (!$id && (!is_numeric($initial) || (float)$initial < 0 || (float)$initial > 1000000)) $errors['initial_stock'] = 'Enter a valid opening quantity.';
    if ($errors) json_error('Please check the ingredient details.', 422, ['fields'=>$errors]);
    if ($id) {
        $st = $pdo->prepare('UPDATE ingredients SET name=?,unit=?,min_stock=?,cost_per_unit=? WHERE id=? AND restaurant_id=?');
        $st->execute([$name,$unit,(float)$min,(float)$cost,$id,$rid]);
        if (!$st->rowCount()) {
            $exists=$pdo->prepare('SELECT 1 FROM ingredients WHERE id=? AND restaurant_id=?'); $exists->execute([$id,$rid]);
            if (!$exists->fetchColumn()) json_error('Ingredient not found.', 404);
        }
        log_activity('inventory.ingredient_updated',(int)$u['id'],$rid,'ingredient',$id);
        json_out(['ok'=>true,'message'=>'Ingredient details saved.']);
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO ingredients (restaurant_id,name,unit,current_stock,min_stock,cost_per_unit) VALUES (?,?,?,?,?,?)')
            ->execute([$rid,$name,$unit,(float)$initial,(float)$min,(float)$cost]);
        $id=(int)$pdo->lastInsertId();
        if ((float)$initial > 0) $pdo->prepare("INSERT INTO stock_movements (restaurant_id,ingredient_id,quantity,type,notes,user_id) VALUES (?,?,?,'adjustment','Opening stock',?)")
            ->execute([$rid,$id,(float)$initial,(int)$u['id']]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    log_activity('inventory.ingredient_created',(int)$u['id'],$rid,'ingredient',$id);
    json_out(['ok'=>true,'message'=>'Ingredient added to inventory.']);
}

if ($action === 'adjust' || $action === 'waste') {
    $id=(int)($in['ingredient_id'] ?? 0); $value=$action === 'adjust' ? ($in['quantity'] ?? '') : ($in['quantity'] ?? '');
    if ($id < 1 || !is_numeric($value) || (float)$value === 0.0 || abs((float)$value) > 1000000) json_error('Enter a valid non-zero quantity.',422);
    $delta=$action === 'waste' ? -(float)$value : (float)$value;
    if ($action === 'waste' && (float)$value < 0) json_error('Waste quantity must be positive.',422);
    $notes=clean_str($in['notes'] ?? '',255) ?: null;
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT current_stock FROM ingredients WHERE id=? AND restaurant_id=? FOR UPDATE'); $q->execute([$id,$rid]); $stock=$q->fetchColumn();
        if ($stock === false) { $pdo->rollBack(); json_error('Ingredient not found.',404); }
        if ((float)$stock+$delta < 0) { $pdo->rollBack(); json_error('This movement would make stock negative.',422); }
        if ((float)$stock+$delta > 999999999.999) { $pdo->rollBack(); json_error('This movement exceeds the maximum stock quantity.',422); }
        $pdo->prepare('UPDATE ingredients SET current_stock=current_stock+? WHERE id=? AND restaurant_id=?')->execute([$delta,$id,$rid]);
        $type=$action === 'waste' ? 'waste' : 'adjustment';
        $pdo->prepare('INSERT INTO stock_movements (restaurant_id,ingredient_id,quantity,type,notes,user_id) VALUES (?,?,?,?,?,?)')->execute([$rid,$id,$delta,$type,$notes,(int)$u['id']]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    log_activity('inventory.' . ($action === 'waste' ? 'waste_recorded' : 'stock_adjusted'),(int)$u['id'],$rid,'ingredient',$id);
    json_out(['ok'=>true,'message'=>$action === 'waste' ? 'Waste recorded and stock updated.' : 'Stock adjustment saved.']);
}

if ($action === 'add_supplier') {
    $name=clean_str($in['name'] ?? '',150); $phone=clean_str($in['phone'] ?? '',30); $email=strtolower(clean_str($in['email'] ?? '',150));
    $errors=[];
    if (mb_strlen($name)<2) $errors['name']='Enter a supplier name.';
    if ($phone!=='' && !preg_match('/^[0-9+().\-\s]{7,30}$/',$phone)) $errors['phone']='Enter a valid phone number.';
    if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) $errors['email']='Enter a valid email address.';
    if ($errors) json_error('Please check the supplier details.',422,['fields'=>$errors]);
    $pdo->prepare('INSERT INTO suppliers (restaurant_id,name,phone,email) VALUES (?,?,?,?)')->execute([$rid,$name,$phone ?: null,$email ?: null]);
    $id=(int)$pdo->lastInsertId(); log_activity('inventory.supplier_created',(int)$u['id'],$rid,'supplier',$id);
    json_out(['ok'=>true,'message'=>'Supplier added.']);
}

if ($action === 'receive_purchase') {
    $supplierId=(int)($in['supplier_id'] ?? 0); $date=clean_str($in['purchased_at'] ?? '',10); $notes=clean_str($in['notes'] ?? '',2000) ?: null;
    $items=$in['items'] ?? [];
    if (!is_array($items) || !$items || count($items)>50) json_error('Add between 1 and 50 purchase items.',422);
    if ($supplierId) { $sq=$pdo->prepare('SELECT 1 FROM suppliers WHERE id=? AND restaurant_id=? AND is_active=1'); $sq->execute([$supplierId,$rid]); if (!$sq->fetchColumn()) json_error('Choose a supplier from this restaurant.',422); }
    if ($date==='') $date=date('Y-m-d');
    $dateObj=DateTime::createFromFormat('!Y-m-d',$date);
    if (!$dateObj || $dateObj->format('Y-m-d')!==$date) json_error('Enter a valid purchase date.',422);
    if ($dateObj->format('Y-m-d')>date('Y-m-d')) json_error('Purchase date cannot be in the future.',422);
    $normalized=[];
    foreach ($items as $i=>$item) {
        $ingredientId=(int)($item['ingredient_id'] ?? 0); $quantity=$item['quantity'] ?? ''; $unitCost=$item['unit_cost'] ?? '';
        if ($ingredientId<1 || !is_numeric($quantity) || (float)$quantity<=0 || (float)$quantity>1000000 || !is_numeric($unitCost) || (float)$unitCost<0 || (float)$unitCost>10000000 || ((float)$quantity*(float)$unitCost)>9999999999.99) json_error('Check each purchase item and its quantity and cost.',422);
        $normalized[]=['id'=>$ingredientId,'quantity'=>(float)$quantity,'cost'=>(float)$unitCost];
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO purchases (restaurant_id,supplier_id,total_amount,status,purchased_at,notes) VALUES (?,?,0,'received',?,?)")
            ->execute([$rid,$supplierId ?: null,$date,$notes]);
        $purchaseId=(int)$pdo->lastInsertId(); $total=0.0;
        foreach ($normalized as $item) {
            $iq=$pdo->prepare('SELECT current_stock,cost_per_unit FROM ingredients WHERE id=? AND restaurant_id=? FOR UPDATE');
            $iq->execute([$item['id'],$rid]); $ingredient=$iq->fetch();
            if (!$ingredient) { $pdo->rollBack(); json_error('One of the selected ingredients was not found in this restaurant.',404); }
            $qty=$item['quantity']; $cost=$item['cost']; $line=round($qty*$cost,2); $total+=$line;
            $oldQty=(float)$ingredient['current_stock']; $oldCost=(float)$ingredient['cost_per_unit'];
            if ($oldQty+$qty>999999999.999 || $total>9999999999.99) { $pdo->rollBack(); json_error('This purchase exceeds the supported stock or purchase value.',422); }
            $newCost=($oldQty+$qty)>0 ? (($oldQty*$oldCost)+($qty*$cost))/($oldQty+$qty) : $cost;
            $pdo->prepare('UPDATE ingredients SET current_stock=current_stock+?,cost_per_unit=? WHERE id=? AND restaurant_id=?')->execute([$qty,$newCost,$item['id'],$rid]);
            $pdo->prepare('INSERT INTO purchase_items (purchase_id,ingredient_id,quantity,unit_cost,line_total) VALUES (?,?,?,?,?)')->execute([$purchaseId,$item['id'],$qty,$cost,$line]);
            $pdo->prepare("INSERT INTO stock_movements (restaurant_id,ingredient_id,quantity,type,reference_id,notes,user_id) VALUES (?,?,?,'purchase',?,?,?)")
                ->execute([$rid,$item['id'],$qty,$purchaseId,'Purchase #' . $purchaseId,(int)$u['id']]);
        }
        $pdo->prepare('UPDATE purchases SET total_amount=? WHERE id=? AND restaurant_id=?')->execute([$total,$purchaseId,$rid]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    log_activity('inventory.purchase_received',(int)$u['id'],$rid,'purchase',$purchaseId);
    json_out(['ok'=>true,'message'=>'Purchase received and stock updated.','purchase_id'=>$purchaseId]);
}

json_error('Unknown inventory action.',422);
