<?php require_once __DIR__ . '/../includes/page.php';
form_page('Create your account', 'Order food, book tables and track orders.', [
  ['full_name','Full name','text','name',1], ['email','Email','email','email',1],
  ['phone','Phone','tel','tel',0], ['password','Password (8+ characters)','password','new-password',1]
], 'auth/register.php', 'Create account', 'Already registered? <a href="login.php">Log in</a> · <a href="../">Back home</a>');
