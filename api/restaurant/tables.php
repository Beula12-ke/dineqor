<?php
// GET: tables + QR codes + scan stats.  POST {action: generate|add|update|delete}
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/qr.php';
$u = require_permission('manage_tables');
$rid = tenant_id($u);
$rs = db()->prepare('SELECT slug FROM restaurants WHERE id=? AND deleted_at IS NULL'); $rs->execute([$rid]);
$slug = $rs->fetchColumn();
if (!$slug) json_error('Restaurant not found.', 404);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    start_secure_session(); require_csrf();
    $in = json_input();
    $action = (string)($in['action'] ?? '');
    $pdo = db();
    $role = (string)($u['staff_role'] ?? '');
    $canManageTables = in_array($role, ['owner', 'manager'], true);
    if ($action === 'status') {
        if (!in_array($role, ['owner', 'manager', 'waiter'], true)) json_error('You do not have permission to update table status.', 403);
        $status = (string)($in['status'] ?? '');
        if (!in_array($status, ['available', 'occupied', 'reserved'], true)) json_error('Choose available, occupied, or booked.', 422);
        $st = $pdo->prepare('UPDATE restaurant_tables SET status=? WHERE id=? AND restaurant_id=?');
        $st->execute([$status, (int)($in['id'] ?? 0), $rid]);
        if (!$st->rowCount()) {
            $check = $pdo->prepare('SELECT id FROM restaurant_tables WHERE id=? AND restaurant_id=?');
            $check->execute([(int)($in['id'] ?? 0), $rid]);
            if (!$check->fetch()) json_error('Table not found.', 404);
        }
        log_activity('table.status_updated', (int)$u['id'], $rid, 'table', (int)($in['id'] ?? 0), 'Set table status to ' . $status);
        json_out(['ok' => true]);
    }
    if (!$canManageTables) json_error('Only an owner or manager can create, edit, or delete tables.', 403);
    try {
        if ($action === 'generate') {                       // "Number of tables: 30"
            $count = (int)($in['count'] ?? 0);
            if ($count < 1 || $count > 200) json_error('Enter a number of tables between 1 and 200.', 422);
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT COALESCE(MAX(table_number),0) FROM restaurant_tables WHERE restaurant_id=? FOR UPDATE');
            $q->execute([$rid]); $start = (int)$q->fetchColumn();
            for ($i = 1; $i <= $count; $i++) create_table_with_qr($rid, $slug, $start + $i);
            $pdo->commit();
            log_activity('tables.generate', (int)$u['id'], $rid, null, null, "Generated $count tables");
        } elseif ($action === 'add') {
            $cap = max(1, min(50, (int)($in['capacity'] ?? 4)));
            $section = clean_str($in['section'] ?? '', 50) ?: null;
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT COALESCE(MAX(table_number),0)+1 FROM restaurant_tables WHERE restaurant_id=? FOR UPDATE');
            $q->execute([$rid]);
            $tid = create_table_with_qr($rid, $slug, (int)$q->fetchColumn(), clean_str($in['label'] ?? '', 50) ?: null, $cap, $section);
            $pdo->commit();
            log_activity('table.add', (int)$u['id'], $rid, 'table', $tid);
        } elseif ($action === 'update') {
            $st = $pdo->prepare('UPDATE restaurant_tables SET label=?, capacity=?, section=? WHERE id=? AND restaurant_id=?');
            $st->execute([clean_str($in['label'] ?? '', 50) ?: null, max(1, min(50, (int)($in['capacity'] ?? 4))),
                          clean_str($in['section'] ?? '', 50) ?: null, (int)($in['id'] ?? 0), $rid]);
        } elseif ($action === 'delete') {
            $st = $pdo->prepare('DELETE FROM restaurant_tables WHERE id=? AND restaurant_id=?');
            $st->execute([(int)($in['id'] ?? 0), $rid]);
            if (!$st->rowCount()) json_error('Table not found.', 404);
            log_activity('table.delete', (int)$u['id'], $rid, 'table', (int)$in['id']);
        } else json_error('Invalid request.', 422);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('tables: ' . $e->getMessage());
        json_error('Something went wrong. Please try again.', 500);
    }
    json_out(['ok' => true]);
}

ensure_restaurant_qr($rid, $slug);
$t = db()->prepare("SELECT t.id, t.table_number, t.label, t.capacity, t.section, t.status,
        q.id AS qr_id, q.target_url, q.scan_count, q.is_active,
        (SELECT COUNT(*) FROM orders o WHERE o.table_id = t.id AND o.restaurant_id = t.restaurant_id) AS orders
    FROM restaurant_tables t LEFT JOIN qr_codes q ON q.table_id = t.id AND q.qr_type='table'
    WHERE t.restaurant_id=? ORDER BY t.table_number");
$t->execute([$rid]);
$r = db()->prepare("SELECT id, target_url, scan_count, is_active FROM qr_codes WHERE restaurant_id=? AND qr_type='restaurant' LIMIT 1");
$r->execute([$rid]);
$s = db()->prepare("SELECT COUNT(*) AS total, SUM(scanned_at >= CURDATE()) AS today,
        SUM(scanned_at >= NOW() - INTERVAL 7 DAY) AS week, SUM(scanned_at >= NOW() - INTERVAL 30 DAY) AS month
        FROM qr_scans WHERE restaurant_id=?");
$s->execute([$rid]);
$stats = $s->fetch();
json_out(['ok' => true, 'csrf' => csrf_token(), 'restaurant_qr' => $r->fetch(), 'tables' => $t->fetchAll(),
          'stats' => array_map('intval', $stats), 'base' => BASE_URL, 'role' => $u['staff_role'] ?? '']);
