<?php
// Shared public-site navigation and footer. Requires includes/page.php.
function public_nav(string $active = 'home'): void {
    $u = current_user();
    $items = [
        'home' => ['Home', url()],
        'restaurants' => ['Restaurants', url('restaurants.php')],
        'features' => ['Features', url('features.php')],
        'about' => ['About', url('about.php')],
        'contact' => ['Contact', url('contact.php')],
    ];
    echo '<header class="home-nav">'
       . '<a class="home-brand" href="' . e(url()) . '" aria-label="Dineqor home">'
       . '<svg class="home-brand-icon" viewBox="0 0 64 64" aria-hidden="true"><path d="M13 6v20m-7-20v11m14-11v11M6 17h14M13 26v32m26-52c10 0 17 9 17 20v26H39V26c0-11 0-20 0-20Z" fill="none" stroke="currentColor" stroke-width="4.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
       . '<span><b>' . e(platform_setting('platform_name', APP_NAME)) . '</b><small>Good Food. Great Business.</small></span></a>'
       . '<nav class="home-nav-links" aria-label="Main navigation">';
    foreach ($items as $key => [$label, $href]) {
        echo '<a' . ($active === $key ? ' class="is-active" aria-current="page"' : '') . ' href="' . e($href) . '">' . e($label) . '</a>';
    }
    echo '</nav>';
    if ($u) echo '<a class="home-login" href="' . e(dashboard_for($u)) . '">My account <span aria-hidden="true">→</span></a>';
    else echo '<a class="home-login" href="' . e(url('auth/login.php')) . '">Login</a>';
    echo '</header>';
}

function public_footer(): void {
    echo '<footer class="site-footer"><div class="wrap site-footer-main">'
       . '<div class="site-footer-brand"><a class="logo" href="' . e(url()) . '">' . e(platform_setting('platform_name', APP_NAME)) . '</a><p>Good food and local restaurants, all in one place.</p></div>'
       . '<nav class="site-footer-links" aria-label="Footer navigation">'
       . '<div><b>Explore</b><a href="' . e(url('restaurants.php')) . '">Restaurants</a><a href="' . e(url('features.php')) . '">Features</a><a href="' . e(url('about.php')) . '">About</a></div>'
       . '<div><b>Your account</b><a href="' . e(url('auth/login.php')) . '">Log in</a><a href="' . e(url('auth/register.php')) . '">Create an account</a></div>'
       . '<div><b>For restaurants</b><a href="' . e(url('register-restaurant.php')) . '">Become a partner</a>';
    if ($email = platform_setting('support_email')) echo '<a href="mailto:' . e($email) . '">' . e($email) . '</a>';
    if ($phone = platform_setting('support_phone')) echo '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $phone)) . '">' . e($phone) . '</a>';
    echo '</div></nav></div><div class="site-footer-bottom"><div class="wrap"><span>© ' . date('Y') . ' ' . e(platform_setting('platform_name', APP_NAME)) . '</span><a href="' . e(url()) . '">Back home ↑</a></div></div></footer>';
}
