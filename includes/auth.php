<?php
require_once __DIR__ . '/security.php';

// Loads the logged-in user (and staff/restaurant context) from the SERVER session only.
function current_user(): ?array {
    start_secure_session();
    if (empty($_SESSION['uid'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;

    $st = db()->prepare('SELECT id, email, full_name, phone, user_type, status, password_changed_at, password_expires_at FROM users
                         WHERE id = ? AND deleted_at IS NULL');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u || $u['status'] !== 'active') { $_SESSION = []; return null; }

    $passwordVersion = (string)($u['password_changed_at'] ?? '');
    if (in_array($u['user_type'], ['restaurant_staff', 'customer'], true) && isset($_SESSION['staff_password_version'])
        && !hash_equals((string)$_SESSION['staff_password_version'], $passwordVersion)) {
        $_SESSION = [];
        if ($u['user_type'] === 'restaurant_staff') $GLOBALS['__staff_password_reset'] = true;
        else $GLOBALS['__customer_password_reset'] = true;
        return null;
    }
    if ($u['user_type'] === 'restaurant_staff') $_SESSION['staff_password_version'] = $passwordVersion;
    $u['password_change_required'] = $u['user_type'] === 'restaurant_staff'
        && !empty($u['password_expires_at']) && strtotime((string)$u['password_expires_at']) <= time();

    $u['restaurant_id'] = null;
    $u['staff_role'] = null;
    if ($u['user_type'] === 'restaurant_owner') {
        $r = db()->prepare('SELECT id FROM restaurants WHERE owner_user_id = ? AND deleted_at IS NULL LIMIT 1');
        $r->execute([$u['id']]);
        $u['restaurant_id'] = ($row = $r->fetch()) ? (int)$row['id'] : null;
        $u['staff_role'] = 'owner';
    } elseif ($u['user_type'] === 'restaurant_staff') {
        $r = db()->prepare("SELECT restaurant_id, role FROM staff WHERE user_id = ? AND status='active' LIMIT 1");
        $r->execute([$u['id']]);
        if ($row = $r->fetch()) { $u['restaurant_id'] = (int)$row['restaurant_id']; $u['staff_role'] = $row['role']; }
    }
    return $cache = $u;
}

function require_login(): array {
    $u = current_user();
    if (!$u && !empty($GLOBALS['__staff_password_reset'])) json_error('Your password was changed by the restaurant owner or manager. Sign in with the new password.', 401, ['redirect' => BASE_URL . '/auth/login.php']);
    if (!$u && !empty($GLOBALS['__customer_password_reset'])) json_error('Your password was reset. Please sign in with your new password.', 401, ['redirect' => BASE_URL . '/auth/login.php']);
    if (!$u) json_error('Please log in.', 401);
    return $u;
}

function require_user_type(string ...$types): array {
    $u = require_login();
    if (!in_array($u['user_type'], $types, true)) json_error('Access denied.', 403);
    if (!empty($u['password_change_required'])) json_error('Your password expired. Ask the restaurant owner or manager to set a new one.', 428, ['redirect' => BASE_URL . '/auth/password-expired.php']);
    return $u;
}

function user_permissions(array $u): array {
    if ($u['user_type'] === 'platform_admin') return ['*'];
    if (!$u['staff_role']) return [];
    $st = db()->prepare('SELECT p.code FROM role_permissions rp
                         JOIN permissions p ON p.id = rp.permission_id WHERE rp.role = ?');
    $st->execute([$u['staff_role']]);
    return array_column($st->fetchAll(), 'code');
}

function has_permission(array $u, string $perm): bool {
    $p = user_permissions($u);
    return in_array('*', $p, true) || in_array($perm, $p, true);
}

function require_permission(string $perm): array {
    $u = require_login();
    if (!empty($u['password_change_required'])) json_error('Your password expired. Ask the restaurant owner or manager to set a new one.', 428, ['redirect' => BASE_URL . '/auth/password-expired.php']);
    if (!has_permission($u, $perm)) json_error('You do not have permission for this action.', 403);
    return $u;
}

// Returns the restaurant_id the logged-in staff/owner belongs to. NEVER from the browser.
function tenant_id(array $u): int {
    if (empty($u['restaurant_id'])) json_error('No restaurant is linked to this account.', 403);
    return (int)$u['restaurant_id'];
}

function login_user(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $userId;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $version = db()->prepare('SELECT password_changed_at FROM users WHERE id=?');
    $version->execute([$userId]);
    $_SESSION['staff_password_version'] = (string)($version->fetchColumn() ?: '');
    db()->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')
        ->execute([client_ip_bin(), $userId]);
}

function dashboard_for(array $u): string {
    return match ($u['user_type']) {
        'platform_admin'   => BASE_URL . '/admin/dashboard.php',
        'restaurant_owner' => BASE_URL . '/restaurant/dashboard.php',
        'restaurant_staff' => BASE_URL . '/staff/dashboard.php',
        default            => BASE_URL . '/customer/dashboard.php',
    };
}
