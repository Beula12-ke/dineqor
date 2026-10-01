<?php
require_once __DIR__ . '/../includes/page.php';
form_page('Forgot your password?', 'Enter the email address for your customer account. If it matches, we will send a secure reset link.', [
  ['email', 'Email', 'email', 'email', 1]
], 'auth/password-reset-request.php', 'Send reset link',
  'Remembered your password? <a href="login.php">Back to log in</a><br>Restaurant staff should ask their owner or manager to reset their password.');
