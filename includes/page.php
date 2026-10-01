<?php
// Shared helpers for server-rendered pages
require_once __DIR__ . '/auth.php';

function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function platform_setting(string $key, string $fallback = ''): string {
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM platform_settings')->fetchAll() as $row) {
                $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) { $settings = []; }
    }
    return $settings[$key] ?? $fallback;
}

// Server-side page guard: redirects BEFORE any HTML is sent
function page_guard(array $types): array {
    $u = current_user();
    if (!$u || !in_array($u['user_type'], $types, true)) {
        header('Location: ' . url('auth/login.php')); exit;
    }
    if (!empty($u['password_change_required']) && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'password-expired.php') {
        header('Location: ' . url('auth/password-expired.php')); exit;
    }
    return $u;
}

function page_head(string $title, string $extraCss = '', string $bodyClass = ''): void {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($title) . ' · ' . e(platform_setting('platform_name', APP_NAME)) . '</title>'
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
       . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=Figtree:wght@400;600;700&display=swap">'
       . '<link rel="stylesheet" href="' . url('assets/css/app.css') . '">'
       . ($extraCss ? '<style>' . $extraCss . '</style>' : '')
       . '</head><body class="' . e($bodyClass) . '">';
}

function page_foot(string ...$scripts): void {
    echo '<script>window.BASE=' . json_encode(BASE_URL) . ';</script>'
       . '<script src="' . url('assets/js/app.js') . '"></script>';
    foreach ($scripts as $s) echo '<script src="' . url('assets/js/' . $s) . '"></script>';
    echo '</body></html>';
}

function nav_bar(?array $u): void {
    $scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $isWorkspacePath = (bool)preg_match('~/(restaurant|staff|admin|customer)/~', $scriptPath);
    echo '<header class="nav"><a class="logo" href="' . url() . '">' . e(platform_setting('platform_name', APP_NAME)) . '</a><nav>';
    if ($u && $isWorkspacePath) {
        $workspaceRole = match ($u['user_type']) {
            'platform_admin' => 'Platform administrator',
            'customer' => 'Customer account',
            'restaurant_owner' => 'Restaurant owner',
            default => ucwords(str_replace('_', ' ', (string)($u['staff_role'] ?? 'team member'))),
        };
        $workspaceName = (string)($u['full_name'] ?: 'Account');
        echo '<span class="workspace-top-user"><span class="workspace-top-avatar">' . e(mb_strtoupper(mb_substr($workspaceName, 0, 1))) . '</span><span><b>' . e($workspaceName) . '</b><small>' . e($workspaceRole) . '</small></span></span>';
    }
    if (!$isWorkspacePath) {
        echo '<a class="nav-discover" href="' . url() . '#restaurants">Restaurants</a>'
           . '<a class="nav-discover" href="' . url() . '#how-it-works">How it works</a>';
    }
    if ($u) {
        if (!$isWorkspacePath) {
            $portalLabel = $u['user_type'] === 'customer' ? 'Customer portal' : 'Dashboard';
            echo '<a href="' . e(dashboard_for($u)) . '">' . e($portalLabel) . '</a>';
        }
        if (!$isWorkspacePath) echo '<a href="#" onclick="logout();return false">Log out</a>';
    } else {
        echo '<a href="' . url('auth/login.php') . '">Log in</a><a href="' . url('auth/register.php') . '">Register</a>'
           . '<a class="pill" href="' . url('register-restaurant.php') . '">Partner with us</a>';
    }
    echo '</nav></header>';

    // Keep the signed-in workspace navigation present while each section loads.
    if (!$u) return;
    if (!$isWorkspacePath) return;
    $script = basename($scriptPath);
    $active = match ($script) {
        'menu.php' => 'menu', 'orders.php' => 'orders', 'reservations.php' => 'reservations', 'settings.php' => 'settings', 'inventory.php' => 'inventory', 'loyalty.php' => 'loyalty', 'subscriptions.php' => 'subscriptions', 'staff.php' => 'staff', 'kitchen.php' => 'kitchen', 'service.php' => 'service', 'reports.php' => 'reports',
        'pos.php' => 'pos', 'qr.php' => 'tables', default => 'overview',
    };
    if ($u['user_type'] === 'platform_admin') {
        echo '<aside class="workspace-side"><div class="side-brand"><span class="side-brand-mark">D</span><span><b>' . e(platform_setting('platform_name', APP_NAME)) . '</b><small>PLATFORM ADMIN</small></span></div>'
           . '<div class="side-label">MANAGE PLATFORM</div><a class="side-link ' . ($active === 'overview' ? 'active' : '') . '" href="' . e(url('admin/dashboard.php')) . '"' . ($active === 'overview' ? ' aria-current="page"' : '') . '><span>◫</span> Overview</a>'
           . '<a class="side-link" href="' . e(url('admin/dashboard.php#restaurants')) . '"><span>▤</span> Restaurants <i class="nav-count" id="pendingBadge">—</i></a>'
           . '<a class="side-link ' . ($active === 'subscriptions' ? 'active' : '') . '" href="' . e(url('admin/subscriptions.php')) . '"><span>＄</span> Subscription plans</a>'
           . '<a class="side-link ' . ($script === 'settings.php' ? 'active' : '') . '" href="' . e(url('admin/settings.php')) . '"' . ($script === 'settings.php' ? ' aria-current="page"' : '') . '><span>⚙</span> Settings</a>'
           . '<a class="side-link" href="' . e(url()) . '"><span>⌂</span> Public website</a>'
           . '<div class="workspace-account"><span class="account-avatar">' . e(mb_strtoupper(mb_substr((string)($u['full_name'] ?: 'A'), 0, 1))) . '</span><span class="account-name"><b>' . e($u['full_name'] ?: 'Administrator') . '</b><small>Platform administrator</small></span><button class="workspace-logout" type="button" onclick="logout()" aria-label="Log out"><span aria-hidden="true">↪</span><span class="logout-label">Log out</span></button></div></aside>';
        return;
    }
    if ($u['user_type'] === 'customer') {
        $customerActive = match (basename($scriptPath)) {
        'orders.php', 'order.php' => 'orders', 'notifications.php' => 'notifications', 'reservations.php' => 'reservations', 'saved.php' => 'saved', 'profile.php' => 'profile', 'reviews.php' => 'reviews', 'rewards.php' => 'rewards', default => 'overview',
        };
        $customerLink = static fn(string $key, string $label, string $icon, string $target): string => '<a class="side-link' . ($customerActive === $key ? ' active' : '') . '" href="' . e(url($target)) . '"' . ($customerActive === $key ? ' aria-current="page"' : '') . '><span>' . e($icon) . '</span> ' . e($label) . '</a>';
        echo '<aside class="workspace-side customer-side"><div class="side-brand"><span class="side-brand-mark">D</span><span><b>Dineqor</b><small>YOUR ACCOUNT</small></span></div>'
           . '<div class="side-label">MY DINEQOR</div>'
           . $customerLink('overview', 'Overview', '◫', 'customer/dashboard.php')
           . $customerLink('orders', 'My orders', '▤', 'customer/orders.php')
           . '<a class="side-link' . ($customerActive === 'notifications' ? ' active' : '') . '" href="' . e(url('customer/notifications.php')) . '"' . ($customerActive === 'notifications' ? ' aria-current="page"' : '') . '><span>♧</span> Order updates <i class="nav-count" data-notification-count hidden>0</i></a>'
           . $customerLink('reservations', 'Reservations', '▣', 'customer/reservations.php')
           . $customerLink('saved', 'Saved places', '♡', 'customer/saved.php')
           . $customerLink('rewards', 'My rewards', '★', 'customer/rewards.php')
           . $customerLink('reviews', 'My reviews', '★', 'customer/reviews.php')
           . $customerLink('profile', 'Profile & security', '⚙', 'customer/profile.php')
           . '<div class="workspace-account"><span class="account-avatar">' . e(mb_strtoupper(mb_substr((string)($u['full_name'] ?: 'C'), 0, 1))) . '</span><span class="account-name"><b>' . e($u['full_name'] ?: 'Customer') . '</b><small>Customer account</small></span><button class="workspace-logout" type="button" onclick="logout()" aria-label="Log out"><span aria-hidden="true">↪</span><span class="logout-label">Log out</span></button></div></aside>'
           . '<script defer src="' . e(url('assets/js/customer-notifications-badge.js')) . '"></script>';
        return;
    }
    if (!in_array($u['user_type'], ['restaurant_owner', 'restaurant_staff'], true)) return;
    $perms = user_permissions($u);
    $can = static fn(string $code): bool => in_array('*', $perms, true) || in_array($code, $perms, true);
    $overview = $can('view_reports') ? 'restaurant/dashboard.php' : 'staff/dashboard.php';
    $links = [['overview','◫','Overview',$overview,null]];
    if ($can('view_reports')) $links[] = ['reports','▥','Reports','restaurant/reports.php',null];
    if ($can('view_orders')) $links[] = ['orders','▤','Orders','restaurant/orders.php',null];
    if (in_array($u['staff_role'] ?? '', ['chef', 'manager', 'owner'], true) && $can('view_orders')) $links[] = ['kitchen','◉','Kitchen display','restaurant/kitchen.php',null];
    if (in_array($u['staff_role'] ?? '', ['waiter', 'manager', 'owner'], true) && $can('view_orders') && $can('manage_tables')) $links[] = ['service','♧','Waiter service','staff/service.php',null];
    if ($can('view_products')) $links[] = ['menu','▦','Menu','restaurant/menu.php',null];
    if ($can('manage_reservations')) $links[] = ['reservations','▣','Reservations','restaurant/reservations.php',null];
    if ($can('manage_pos')) $links[] = ['pos','＄','Point of sale','restaurant/pos.php',null];
    if (($can('manage_tables') || $can('manage_qr')) && ($u['staff_role'] ?? '') !== 'waiter') $links[] = ['tables','▧','Tables & QR','restaurant/qr.php',null];
    if ($can('manage_inventory')) $links[] = ['inventory','◩','Inventory','restaurant/inventory.php',null];
    if ($can('manage_settings')) $links[] = ['loyalty','★','Loyalty','restaurant/loyalty.php',null];
    if ($can('manage_staff')) $links[] = ['staff','♧','Team','restaurant/staff.php',null];
    if ($can('manage_settings')) $links[] = ['settings','⚙','Settings','restaurant/settings.php',null];
    $restaurantName = 'Restaurant workspace';
    if (!empty($u['restaurant_id'])) {
        $rs = db()->prepare('SELECT name FROM restaurants WHERE id=? AND deleted_at IS NULL'); $rs->execute([(int)$u['restaurant_id']]);
        $restaurantName = (string)($rs->fetchColumn() ?: $restaurantName);
    }
    echo '<aside class="workspace-side"><div class="side-brand"><span class="side-brand-mark">D</span><span><b>' . e(platform_setting('platform_name', APP_NAME)) . '</b><small>RESTAURANT WORKSPACE</small></span></div><div class="side-workspace">'
       . '<span class="side-avatar">' . e(mb_strtoupper(mb_substr($restaurantName, 0, 1))) . '</span><span><b>' . e($restaurantName) . '</b><small>' . e(ucwords(str_replace('_', ' ', $u['staff_role'] ?? 'team'))) . '</small></span></div>'
       . '<div class="side-label">WORKSPACE</div>';
    foreach ($links as [$key, $icon, $label, $href]) {
        $on = $key === $active;
        echo '<a class="side-link' . ($on ? ' active' : '') . '" href="' . e(url($href)) . '"' . ($on ? ' aria-current="page"' : '') . '><span>' . e($icon) . '</span> ' . e($label) . '</a>';
    }
    echo '<div class="workspace-account"><span class="account-avatar">' . e(mb_strtoupper(mb_substr($restaurantName, 0, 1))) . '</span><span class="account-name"><b>' . e($u['full_name'] ?: 'Team member') . '</b><small>' . e(ucwords(str_replace('_', ' ', $u['staff_role'] ?? 'team'))) . '</small></span><button class="workspace-logout" type="button" onclick="logout()" aria-label="Log out"><span aria-hidden="true">↪</span><span class="logout-label">Log out</span></button></div></aside>';
}

// Renders an auth/register style form page. $fields = [name,label,type,autocomplete,required]
function form_page(string $title, string $sub, array $fields, string $endpoint, string $btn, string $altHtml): void {
    $u = current_user();
    if ($u) { header('Location: ' . dashboard_for($u)); exit; }
    page_head($title);
    echo '<div class="auth-wrap"><form class="card" id="f" novalidate><a class="logo" href="' . url() . '">Dine<span>qor</span></a>';
    $authTabs = [
        ['auth/login.php', 'Log in'],
        ['auth/register.php', 'Create account'],
        ['register-restaurant.php', 'Partner restaurant'],
    ];
    if (in_array($endpoint, ['auth/login.php', 'auth/register.php', 'restaurants/register.php'], true)) {
        $activeTab = match ($endpoint) {
            'auth/login.php' => 'auth/login.php',
            'auth/register.php' => 'auth/register.php',
            default => 'register-restaurant.php',
        };
        echo '<nav class="auth-tabs" aria-label="Account options">';
        foreach ($authTabs as [$target, $label]) {
            $active = $target === $activeTab;
            echo '<a href="' . e(url($target)) . '"' . ($active ? ' class="active" aria-current="page"' : '') . '>' . e($label) . '</a>';
        }
        echo '</nav>';
    }
    echo '<h1>' . e($title) . '</h1><p class="sub">' . e($sub) . '</p>';
    foreach ($fields as [$n, $l, $t, $ac, $req]) {
        echo '<label for="' . $n . '">' . e($l) . '</label>';
        if ($t === 'textarea') {
            echo '<textarea id="' . $n . '" name="' . $n . '" rows="3"></textarea>';
        } else {
            $input = '<input id="' . $n . '" name="' . $n . '" type="' . $t . '" autocomplete="' . $ac . '"' . ($n === 'website_url' ? ' maxlength="2048" placeholder="https://restaurant.com"' : '') . ($req ? ' required' : '') . '>';
            if ($t === 'password') {
                echo '<div class="password-visibility-wrap">' . $input . '<button class="password-visibility" type="button" data-password-toggle aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>';
            } else echo $input;
        }
        echo '<div class="err" data-err="' . $n . '"></div>';
        if ($endpoint === 'auth/login.php' && $n === 'password') echo '<div class="login-forgot"><a href="' . e(url('auth/forgot-password.php')) . '">Forgot password?</a></div>';
    }
    echo '<button class="btn" type="submit">' . e($btn) . '</button><div class="msg" id="msg"></div><div class="alt">' . $altHtml . '</div></form></div>';
    page_foot();
    echo '<script>document.querySelectorAll("[data-password-toggle]").forEach(t=>t.addEventListener("click",()=>{const w=t.closest(".password-visibility-wrap"),i=w.querySelector("input"),visible=i.type==="password";i.type=visible?"text":"password";t.setAttribute("aria-label",visible?"Hide password":"Show password");t.setAttribute("aria-pressed",String(visible));t.innerHTML=visible?"<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.2A10.9 10.9 0 0112 5c6.4 0 10 7 10 7a13.4 13.4 0 01-3.2 3.8M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10.9 10.9 0 004.2-.8\"/></svg>":"<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z\"/><circle cx=\"12\" cy=\"12\" r=\"3\"/></svg>";}));document.getElementById("f").addEventListener("submit",async ev=>{ev.preventDefault();'
       . 'const f=ev.target,b=f.querySelector(".btn"),m=document.getElementById("msg");b.disabled=true;m.className="msg";showFieldErrors(f);'
       . 'const r=await api(' . json_encode($endpoint) . ',{method:"POST",body:formData(f)});b.disabled=false;'
       . 'if(r.ok){m.className="msg ok";m.textContent=r.message||"Success";if(r.redirect)location.href=r.redirect;else f.reset();}'
       . 'else{m.className="msg error";m.textContent=r.error||"Something went wrong.";showFieldErrors(f,r.fields);}});</script>';
}
