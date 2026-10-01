<?php require_once __DIR__ . '/includes/page.php';
form_page('Partner with Dineqor', 'Apply to get your restaurant online. We review every application.', [
  ['restaurant_name','Restaurant name','text','off',1], ['owner_name','Owner name','text','name',1],
  ['email','Email','email','email',1], ['phone','Phone','tel','tel',1], ['city','City','text','address-level2',0],
  ['website_url','Company website (optional)','url','url',0],
  ['cuisine','Cuisine','text','off',0], ['description','Description','textarea','off',0],
  ['password','Password (8+ characters)','password','new-password',1]
], 'restaurants/register.php', 'Submit application', 'Already a partner? <a href="auth/login.php">Log in</a> · <a href="./">Back home</a>');
