<?php
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/auth.php';
if($_SERVER['REQUEST_METHOD']!=='GET')json_error('Method not allowed.',405);
$slug=clean_str($_GET['slug']??'',80);$restaurant=resolve_public_restaurant($slug?:null);if(!$restaurant)json_error('Restaurant not found.',404);$rid=(int)$restaurant['id'];
$q=db()->prepare('SELECT is_enabled,shillings_per_point,points_per_earn,redemption_points,redemption_value FROM loyalty_settings WHERE restaurant_id=?');$q->execute([$rid]);$settings=$q->fetch();
$user=current_user();$customerId=($user&&$user['user_type']==='customer')?(int)$user['id']:0;$balance=0;
if($customerId){$b=db()->prepare('SELECT points_balance FROM loyalty_accounts WHERE restaurant_id=? AND user_id=?');$b->execute([$rid,$customerId]);$balance=(int)($b->fetchColumn()?:0);}
json_out(['ok'=>true,'enabled'=>(bool)($settings['is_enabled']??false),'points'=>$balance,'redemption_points'=>(int)($settings['redemption_points']??100),'redemption_value'=>(float)($settings['redemption_value']??10)]);
