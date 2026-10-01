<?php
// Move an order forward or cancel it. POST {id, action: advance|cancel, reason?}
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orders.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed', 405);
start_secure_session();
require_csrf();
$u = require_permission('manage_orders');
$rid = tenant_id($u);

$in = json_input();
$id = (int)($in['id'] ?? 0);
$action = (string)($in['action'] ?? '');
if ($id < 1 || !in_array($action, ['advance', 'cancel'], true)) json_error('Invalid request.', 422);

$pdo = db();
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('SELECT id, status, order_type, payment_status, payment_method FROM orders WHERE id = ? AND restaurant_id = ? FOR UPDATE');
    $st->execute([$id, $rid]);
    $o = $st->fetch();
    if (!$o) { $pdo->rollBack(); json_error('Order not found.', 404); }

    if (in_array($o['status'], ['completed', 'delivered', 'cancelled'], true)) { $pdo->rollBack(); json_error('This order is already ' . $o['status'] . '.', 409); }

    if ($action === 'advance' && $o['payment_method'] === 'mpesa' && $o['payment_status'] === 'failed') {
        $pdo->rollBack(); json_error('This M-Pesa payment failed. Cancel the order or contact the customer before continuing.', 409);
    }

    if ($action === 'cancel') {
        $to = 'cancelled';
        $reason = clean_str($in['reason'] ?? '', 255) ?: 'Cancelled by restaurant';
        $pdo->prepare('UPDATE orders SET status = ?, cancelled_at = NOW(), cancellation_reason = ? WHERE id = ?')->execute([$to, $reason, $id]);
        $pdo->prepare("UPDATE payments SET status = 'failed', notes = 'Order cancelled' WHERE order_id = ? AND status = 'pending'")->execute([$id]);
        if ($o['payment_method']==='mpesa' && $o['payment_status']!=='paid') {
            $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE id=? AND payment_status='pending'")->execute([$id]);
            refund_mpesa_order_loyalty($pdo,$id);
        }
        restore_recipe_inventory($pdo, $rid, $id, (int)$u['id']);
        $note = $reason;
    } else {
        $to = next_status($o['status'], $o['order_type']);
        if (!$to) { $pdo->rollBack(); json_error('Nothing to do for this order.', 409); }
        if (in_array($to,['completed','delivered'],true) && $o['payment_method']==='mpesa' && $o['payment_status']!=='paid') {
            $pdo->rollBack(); json_error('Wait for Safaricom to confirm this M-Pesa payment before completing the order.',409);
        }
        $col = ['confirmed' => 'accepted_at', 'preparing' => 'preparing_at', 'ready' => 'ready_at', 'completed' => 'completed_at', 'delivered' => 'completed_at'][$to] ?? null;
        $pdo->prepare('UPDATE orders SET status = ?' . ($col ? ", $col = NOW()" : '') . ' WHERE id = ?')->execute([$to, $id]);
        if (in_array($to, ['completed', 'delivered'], true) && $o['payment_method'] === 'cash' && $o['payment_status'] !== 'paid') {
            $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$id]);      // cash is collected on handover
            $pdo->prepare("UPDATE payments SET status = 'completed', paid_at = NOW() WHERE order_id = ? AND status = 'pending'")->execute([$id]);
        }
        $note = null;
    }
    if (in_array($to, ['completed','delivered'], true)) award_order_loyalty($pdo,$rid,$id);
    $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?,?,?,?)')->execute([$id, $to, (int)$u['id'], $note]);
    // Do not roll back the restaurant's order update if the optional notification
    // inbox upgrade has not been installed yet. The customer inbox still requires
    // database/customer-notifications-upgrade.sql.
    try {
        notify_customer_order_status($pdo, $id, $to, $note);
    } catch (Throwable $notificationError) {
        error_log('customer order notification was not saved: ' . $notificationError->getMessage());
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('order action: ' . $e->getMessage());
    json_error('Could not update the order. Please try again.', 500);
}
log_activity('order.' . $action, (int)$u['id'], $rid, 'order', $id, "Order → $to");
json_out(['ok' => true, 'status' => $to]);
