<?php
require_once __DIR__ . '/../config/database.php';

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_name('dineqor_sid');
    session_start();
}

function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400, array $extra = []): never {
    json_out(['ok' => false, 'error' => $message] + $extra, $code);
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw ?: '[]', true);
    return is_array($d) ? $d : [];
}

function csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

// Every state-changing API call must send X-CSRF-Token
function require_csrf(): void {
    start_secure_session();
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        json_error('Security token invalid. Please refresh the page.', 419);
    }
}

function client_ip_bin(): ?string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $bin = @inet_pton($ip);
    return $bin === false ? null : $bin;
}

function log_activity(string $action, ?int $userId, ?int $restaurantId = null,
                      ?string $entityType = null, ?int $entityId = null,
                      ?string $description = null, ?array $meta = null): void {
    try {
        $st = db()->prepare('INSERT INTO activity_logs
            (user_id, restaurant_id, action, entity_type, entity_id, description, meta_json, ip_address, user_agent)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $st->execute([$userId, $restaurantId, $action, $entityType, $entityId, $description,
            $meta ? json_encode($meta) : null, client_ip_bin(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);
    } catch (Throwable $e) { error_log('log_activity: ' . $e->getMessage()); }
}

function clean_str(mixed $v, int $max = 255): string {
    return mb_substr(trim((string)$v), 0, $max);
}

// Only accept explicit secure web addresses for links displayed on a public storefront.
function normalize_https_website_url(mixed $value): string|false {
    $url = trim((string)$value);
    if ($url === '') return '';
    if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return false;
    return $url;
}

function make_slug(string $name): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
    return $s !== '' ? mb_substr($s, 0, 60) : 'restaurant';
}

// Public URL for a stored relative path (uploads etc.). Returns null when empty.
function public_url(?string $path): ?string {
    if ($path === null || $path === '') return null;
    return BASE_URL . '/' . ltrim($path, '/');
}

// Unpredictable 43-char URL-safe token (256 bits) for QR codes
function new_qr_token(): string { return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); }
