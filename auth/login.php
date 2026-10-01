<?php
require_once __DIR__ . '/../includes/page.php';
form_page('Welcome back', 'Log in to your Dineqor account.', [
  ['email', 'Email', 'email', 'username', 1],
  ['password', 'Password', 'password', 'current-password', 1]
], 'auth/login.php', 'Log in',
  '<a href="' . e(url()) . '">Back home</a>');
