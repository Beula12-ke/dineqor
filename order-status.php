<?php
// Guest / customer order tracking page (the token in the URL is signed and verified by the server)
require_once __DIR__ . '/includes/page.php';
page_head('Your order');
nav_bar(current_user());
echo '<main class="wrap" id="track" data-r="' . e($_GET['r'] ?? '') . '" data-n="' . e($_GET['n'] ?? '') . '" data-t="' . e($_GET['t'] ?? '') . '"><div class="skel" style="height:220px"></div></main>';
page_foot('track.js');
