<?php
require_once __DIR__ . '/../../includes/auth.php';
require_user_type('platform_admin');$pdo=db();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $plans=$pdo->query('SELECT id,code,name,description,price AS monthly_price,restaurant_limit,is_active FROM subscription_plans ORDER BY price,name')->fetchAll();
 $restaurants=$pdo->query("SELECT r.id,r.name,r.status,
   (SELECT s.id FROM subscriptions s WHERE s.restaurant_id=r.id AND s.status IN ('active','trialing','past_due') AND s.starts_at<=CURDATE() AND (s.ends_at IS NULL OR s.ends_at>=CURDATE()) ORDER BY s.id DESC LIMIT 1) AS subscription_id,
   (SELECT s.plan_id FROM subscriptions s WHERE s.restaurant_id=r.id AND s.status IN ('active','trialing','past_due') AND s.starts_at<=CURDATE() AND (s.ends_at IS NULL OR s.ends_at>=CURDATE()) ORDER BY s.id DESC LIMIT 1) AS plan_id,
   (SELECT s.status FROM subscriptions s WHERE s.restaurant_id=r.id AND s.status IN ('active','trialing','past_due') AND s.starts_at<=CURDATE() AND (s.ends_at IS NULL OR s.ends_at>=CURDATE()) ORDER BY s.id DESC LIMIT 1) AS subscription_status,
   (SELECT s.ends_at FROM subscriptions s WHERE s.restaurant_id=r.id AND s.status IN ('active','trialing','past_due') AND s.starts_at<=CURDATE() AND (s.ends_at IS NULL OR s.ends_at>=CURDATE()) ORDER BY s.id DESC LIMIT 1) AS ends_at
   FROM restaurants r WHERE r.deleted_at IS NULL ORDER BY r.name")->fetchAll();
 $history=$pdo->query('SELECT s.id,s.restaurant_id,s.plan_id,s.status,s.starts_at,s.ends_at,s.notes,s.created_at,r.name AS restaurant_name,p.name AS plan_name,p.price AS monthly_price FROM subscriptions s JOIN restaurants r ON r.id=s.restaurant_id JOIN subscription_plans p ON p.id=s.plan_id ORDER BY s.created_at DESC LIMIT 100')->fetchAll();
 json_out(['ok'=>true,'csrf'=>csrf_token(),'plans'=>$plans,'restaurants'=>$restaurants,'history'=>$history]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')json_error('Method not allowed.',405);
start_secure_session();require_csrf();$in=json_input();$action=(string)($in['action']??'');$admin=current_user();
if($action==='save_plan'){
 $id=(int)($in['id']??0);$name=clean_str($in['name']??'',100);$description=clean_str($in['description']??'',500);$price=$in['monthly_price']??'';$limit=$in['restaurant_limit']??'';$active=!empty($in['is_active'])?1:0;
 if(mb_strlen($name)<2||!is_numeric($price)||(float)$price<0||(float)$price>99999999.99||!filter_var($limit,FILTER_VALIDATE_INT)||(int)$limit<1||(int)$limit>10000)json_error('Check the plan name, monthly price and restaurant limit.',422);
 if($id){$pdo->prepare('UPDATE subscription_plans SET name=?,description=?,price=?,restaurant_limit=?,is_active=? WHERE id=?')->execute([$name,$description?:null,(float)$price,(int)$limit,$active,$id]);}
 else{$base=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',iconv('UTF-8','ASCII//TRANSLIT',$name)?:$name),'-'));$code=substr($base?:'plan',0,40);$suffix=1;$codeQ=$pdo->prepare('SELECT 1 FROM subscription_plans WHERE code=?');while(true){$codeQ->execute([$code]);if(!$codeQ->fetchColumn())break;$tail='-'.$suffix++;$code=substr($base,0,40-strlen($tail)).$tail;}$pdo->prepare("INSERT INTO subscription_plans (code,name,description,price,currency,billing_cycle,restaurant_limit,is_active) VALUES (?,?,?,?,'KES','monthly',?,?)")->execute([$code,$name,$description?:null,(float)$price,(int)$limit,$active]);$id=(int)$pdo->lastInsertId();}
 log_activity('subscription.plan_saved',(int)$admin['id'],null,'subscription_plan',$id);json_out(['ok'=>true,'message'=>'Subscription plan saved.']);
}
if($action==='assign'){
 $restaurantId=(int)($in['restaurant_id']??0);$planId=(int)($in['plan_id']??0);$status=(string)($in['status']??'active');$start=(string)($in['starts_at']??date('Y-m-d'));$end=trim((string)($in['ends_at']??''));$notes=clean_str($in['notes']??'',500);
 $validDate=static function(string $value):bool{$d=DateTime::createFromFormat('!Y-m-d',$value);return $d&&$d->format('Y-m-d')===$value;};
 if($restaurantId<1||$planId<1||!in_array($status,['trialing','active','past_due','cancelled'],true)||!$validDate($start)||$start>date('Y-m-d')||($end!==''&&(!$validDate($end)||$end<$start)))json_error('Check the restaurant, plan, status and subscription dates. Start dates cannot be in the future.',422);
 $chk=$pdo->prepare('SELECT 1 FROM restaurants WHERE id=? AND deleted_at IS NULL');$chk->execute([$restaurantId]);if(!$chk->fetchColumn())json_error('Restaurant not found.',404);
 $pl=$pdo->prepare('SELECT 1 FROM subscription_plans WHERE id=? AND is_active=1');$pl->execute([$planId]);if(!$pl->fetchColumn())json_error('Choose an active subscription plan.',422);
 $pdo->beginTransaction();try{$pdo->prepare("UPDATE subscriptions SET status='cancelled',cancelled_at=NOW() WHERE restaurant_id=? AND status IN ('active','trialing','past_due')")->execute([$restaurantId]);$pdo->prepare('INSERT INTO subscriptions (restaurant_id,plan_id,status,starts_at,ends_at,notes,assigned_by) VALUES (?,?,?,?,?,?,?)')->execute([$restaurantId,$planId,$status,$start,$end?:null,$notes?:null,(int)$admin['id']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 $id=(int)$pdo->lastInsertId();log_activity('subscription.assigned',(int)$admin['id'],$restaurantId,'subscription',$id);json_out(['ok'=>true,'message'=>'Restaurant subscription updated.']);
}
json_error('Unknown subscription action.',422);
