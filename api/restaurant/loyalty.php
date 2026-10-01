<?php
require_once __DIR__ . '/../../includes/auth.php';
$u=require_permission('manage_settings'); $rid=tenant_id($u); $pdo=db();
if ($_SERVER['REQUEST_METHOD']==='GET') {
    $q=$pdo->prepare('SELECT is_enabled,shillings_per_point,points_per_earn,redemption_points,redemption_value FROM loyalty_settings WHERE restaurant_id=?');
    $q->execute([$rid]); $settings=$q->fetch() ?: ['is_enabled'=>0,'shillings_per_point'=>100,'points_per_earn'=>1,'redemption_points'=>100,'redemption_value'=>'10.00'];
    json_out(['ok'=>true,'csrf'=>csrf_token(),'settings'=>$settings]);
}
if ($_SERVER['REQUEST_METHOD']!=='POST') json_error('Method not allowed.',405);
start_secure_session();require_csrf();$in=json_input();
$enabled=!empty($in['is_enabled'])?1:0;$spend=$in['shillings_per_point']??'';$points=$in['points_per_earn']??'';$redeemPoints=$in['redemption_points']??'';$value=$in['redemption_value']??'';
if (!filter_var($spend,FILTER_VALIDATE_INT) || (int)$spend<1 || (int)$spend>1000000 || !filter_var($points,FILTER_VALIDATE_INT) || (int)$points<1 || (int)$points>10000 || !filter_var($redeemPoints,FILTER_VALIDATE_INT) || (int)$redeemPoints<1 || (int)$redeemPoints>1000000 || !is_numeric($value) || (float)$value<0.01 || (float)$value>1000000) json_error('Enter valid point and reward values.',422);
$pdo->prepare('INSERT INTO loyalty_settings (restaurant_id,is_enabled,shillings_per_point,points_per_earn,redemption_points,redemption_value) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled),shillings_per_point=VALUES(shillings_per_point),points_per_earn=VALUES(points_per_earn),redemption_points=VALUES(redemption_points),redemption_value=VALUES(redemption_value)')->execute([$rid,$enabled,(int)$spend,(int)$points,(int)$redeemPoints,(float)$value]);
log_activity('loyalty.settings_updated',(int)$u['id'],$rid,'loyalty',$rid);
json_out(['ok'=>true,'message'=>$enabled?'Loyalty rewards are enabled.':'Loyalty rewards are paused.']);
