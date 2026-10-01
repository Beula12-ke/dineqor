<?php
// Restaurant owners and managers can manage accounts for their own restaurant only.
require_once __DIR__ . '/../../includes/auth.php';
$u = require_permission('manage_staff');
$rid = tenant_id($u);
$pdo = db();
$roles = ['manager', 'cashier', 'chef', 'waiter', 'delivery', 'inventory'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $st = $pdo->prepare("SELECT s.id, s.role, s.status, s.created_at, u.full_name, u.email, u.phone,
        u.password_expires_at, (u.password_expires_at IS NOT NULL AND u.password_expires_at<=NOW()) AS password_expired
        FROM staff s JOIN users u ON u.id=s.user_id
        WHERE s.restaurant_id=? AND s.role <> 'owner' AND u.deleted_at IS NULL
        ORDER BY s.status='active' DESC, u.full_name, u.email");
    $st->execute([$rid]);
    json_out(['ok' => true, 'staff' => $st->fetchAll(), 'csrf' => csrf_token(), 'roles' => $roles]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_error('Method not allowed.', 405);
start_secure_session(); require_csrf();
$in = json_input();
$action = (string)($in['action'] ?? '');

if ($action === 'add') {
    $name = clean_str($in['full_name'] ?? '', 150);
    $email = strtolower(clean_str($in['email'] ?? '', 150));
    $phone = clean_str($in['phone'] ?? '', 30);
    $password = (string)($in['password'] ?? '');
    $role = (string)($in['role'] ?? '');
    $errors = [];
    if (mb_strlen($name) < 2) $errors['full_name'] = 'Enter the team member’s full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if (strlen($password) < 8) $errors['password'] = 'Use a temporary password with at least 8 characters.';
    if (!in_array($role, $roles, true)) $errors['role'] = 'Choose a valid role.';
    if ($errors) json_error('Please fix the highlighted fields.', 422, ['fields' => $errors]);

    try {
        $pdo->beginTransaction();
        $check = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            $pdo->rollBack();
            json_error('That email already belongs to an account. Use a different email.', 409, ['fields' => ['email' => 'Email already registered.']]);
        }
        $pdo->prepare("INSERT INTO users (email,password_hash,full_name,phone,user_type,password_changed_at,password_expires_at)
            VALUES (?,?,?,?,'restaurant_staff',NOW(),DATE_ADD(NOW(), INTERVAL 90 DAY))")
            ->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name, $phone ?: null]);
        $userId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO staff (user_id,restaurant_id,role,status) VALUES (?,?,?,\'active\')')
            ->execute([$userId, $rid, $role]);
        $staffId = (int)$pdo->lastInsertId();
        $pdo->commit();
        log_activity('staff.created', (int)$u['id'], $rid, 'staff', $staffId, "Added $name as $role");
        json_out(['ok' => true, 'message' => 'Team member added. Their password expires in 90 days. They can contact an owner or manager to set a new password when it expires. Share the password securely.'], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('staff add: ' . $e->getMessage());
        json_error('Could not add this team member. Please try again.', 500);
    }
}

if ($action === 'reset_password') {
    $staffId = filter_var($in['id'] ?? null, FILTER_VALIDATE_INT);
    $password = (string)($in['password'] ?? '');
    if (!$staffId || strlen($password) < 8) json_error('Choose a new password with at least 8 characters.', 422, ['fields'=>['password'=>'Use at least 8 characters.']]);
    try {
        $pdo->beginTransaction();
        $find = $pdo->prepare("SELECT s.user_id,u.full_name,u.password_hash FROM staff s JOIN users u ON u.id=s.user_id WHERE s.id=? AND s.restaurant_id=? AND s.role<>'owner' FOR UPDATE");
        $find->execute([$staffId,$rid]); $member=$find->fetch();
        if (!$member) { $pdo->rollBack(); json_error('Team member not found.',404); }
        if (password_verify($password,(string)$member['password_hash'])) { $pdo->rollBack(); json_error('Choose a different password from the current one.',422,['fields'=>['password'=>'Use a different password.']]); }
        $pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=NOW(),password_expires_at=DATE_ADD(NOW(), INTERVAL 90 DAY) WHERE id=?')
            ->execute([password_hash($password,PASSWORD_DEFAULT),(int)$member['user_id']]);
        $pdo->commit();
        log_activity('staff.password_reset',(int)$u['id'],$rid,'staff',(int)$staffId,'Issued a temporary password for '.$member['full_name']);
        json_out(['ok'=>true,'message'=>'Password updated. It expires in 90 days. Share the new sign-in details securely.']);
    } catch (Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        error_log('staff password reset: '.$e->getMessage());
        json_error('Could not reset this team member’s password. Please try again.',500);
    }
}

if ($action === 'update') {
    $staffId = filter_var($in['id'] ?? null, FILTER_VALIDATE_INT);
    $role = (string)($in['role'] ?? '');
    $status = (string)($in['status'] ?? '');
    if (!$staffId || !in_array($role, $roles, true) || !in_array($status, ['active', 'disabled'], true)) {
        json_error('Choose a valid role and status.', 422);
    }
    $st = $pdo->prepare("UPDATE staff SET role=?,status=? WHERE id=? AND restaurant_id=? AND role <> 'owner'");
    $st->execute([$role, $status, $staffId, $rid]);
    if (!$st->rowCount()) {
        $check = $pdo->prepare("SELECT id FROM staff WHERE id=? AND restaurant_id=? AND role <> 'owner'");
        $check->execute([$staffId, $rid]);
        if (!$check->fetch()) json_error('Team member not found.', 404);
    }
    log_activity('staff.updated', (int)$u['id'], $rid, 'staff', $staffId, "Updated team member role to $role and status to $status");
    json_out(['ok' => true, 'message' => 'Team member updated.']);
}

json_error('Invalid request.', 422);
