<?php
// Run once from terminal:  php tools/set-admin-password.php "YourNewStrongPassword"
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../config/database.php';
$pw = $argv[1] ?? '';
if (strlen($pw) < 10) exit("Usage: php tools/set-admin-password.php \"password (10+ chars)\"\n");
$st = db()->prepare("UPDATE users SET password_hash = ? WHERE user_type = 'platform_admin' AND email = 'admin@dineqor.com'");
$st->execute([password_hash($pw, PASSWORD_DEFAULT)]);
echo $st->rowCount() ? "Admin password updated.\n" : "Admin user not found.\n";
