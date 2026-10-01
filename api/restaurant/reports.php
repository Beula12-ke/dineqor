<?php
// Restaurant-scoped sales and operations reporting.
require_once __DIR__ . '/../../includes/auth.php';
$u=require_permission('view_reports');
$rid=tenant_id($u);
$today=new DateTimeImmutable('today');
$fromRaw=(string)($_GET['from'] ?? $today->modify('-6 days')->format('Y-m-d'));
$toRaw=(string)($_GET['to'] ?? $today->format('Y-m-d'));
$parse=static function(string $value): ?DateTimeImmutable {
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    return $date && $date->format('Y-m-d')===$value ? $date : null;
};
$from=$parse($fromRaw); $to=$parse($toRaw);
if (!$from || !$to) json_error('Choose valid report dates.',422);
if ($from>$to) json_error('The start date must be before the end date.',422);
if ((int)$from->diff($to)->days>365) json_error('Choose a date range of one year or less.',422);
$fromDate=$from->format('Y-m-d'); $toExclusive=$to->modify('+1 day')->format('Y-m-d');
$pdo=db();

$summaryQ=$pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN payment_status='paid' AND status<>'cancelled' THEN total_amount ELSE 0 END),0) AS paid_sales,
    SUM(CASE WHEN payment_status='paid' AND status<>'cancelled' THEN 1 ELSE 0 END) AS paid_orders,
    COALESCE(SUM(CASE WHEN payment_status IN ('unpaid','pending') AND status<>'cancelled' THEN total_amount ELSE 0 END),0) AS outstanding,
    SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
    COUNT(*) AS total_orders
    FROM orders WHERE restaurant_id=? AND created_at>=? AND created_at<?");
$summaryQ->execute([$rid,$fromDate,$toExclusive]); $summary=$summaryQ->fetch();
$summary['paid_sales']=(float)$summary['paid_sales']; $summary['paid_orders']=(int)$summary['paid_orders'];
$summary['outstanding']=(float)$summary['outstanding']; $summary['cancelled_orders']=(int)$summary['cancelled_orders']; $summary['total_orders']=(int)$summary['total_orders'];
$summary['average_paid']=$summary['paid_orders'] ? round($summary['paid_sales']/$summary['paid_orders'],2) : 0.0;

$daily=[];
for ($day=$from;$day<=$to;$day=$day->modify('+1 day')) $daily[$day->format('Y-m-d')]=['date'=>$day->format('Y-m-d'),'orders'=>0,'sales'=>0.0];
$dailyQ=$pdo->prepare("SELECT DATE(created_at) AS day,COUNT(*) AS orders,
    COALESCE(SUM(CASE WHEN payment_status='paid' AND status<>'cancelled' THEN total_amount ELSE 0 END),0) AS sales
    FROM orders WHERE restaurant_id=? AND created_at>=? AND created_at<? GROUP BY DATE(created_at) ORDER BY day");
$dailyQ->execute([$rid,$fromDate,$toExclusive]);
foreach ($dailyQ->fetchAll() as $row) if (isset($daily[$row['day']])) { $daily[$row['day']]['orders']=(int)$row['orders']; $daily[$row['day']]['sales']=(float)$row['sales']; }

$typeQ=$pdo->prepare("SELECT order_type,COUNT(*) AS orders,
    COALESCE(SUM(CASE WHEN payment_status='paid' AND status<>'cancelled' THEN total_amount ELSE 0 END),0) AS sales
    FROM orders WHERE restaurant_id=? AND created_at>=? AND created_at<? AND status<>'cancelled' GROUP BY order_type ORDER BY orders DESC");
$typeQ->execute([$rid,$fromDate,$toExclusive]);
$paymentQ=$pdo->prepare("SELECT COALESCE(NULLIF(payment_method,''),'Unknown') AS method,COUNT(*) AS orders,SUM(total_amount) AS sales
    FROM orders WHERE restaurant_id=? AND created_at>=? AND created_at<? AND payment_status='paid' AND status<>'cancelled' GROUP BY payment_method ORDER BY sales DESC");
$paymentQ->execute([$rid,$fromDate,$toExclusive]);
$statusQ=$pdo->prepare("SELECT status,COUNT(*) AS orders FROM orders WHERE restaurant_id=? AND created_at>=? AND created_at<? GROUP BY status ORDER BY orders DESC");
$statusQ->execute([$rid,$fromDate,$toExclusive]);
$productsQ=$pdo->prepare("SELECT oi.product_name AS name,SUM(oi.quantity) AS quantity,SUM(oi.line_total) AS sales
    FROM order_items oi JOIN orders o ON o.id=oi.order_id
    WHERE o.restaurant_id=? AND o.created_at>=? AND o.created_at<? AND o.payment_status='paid' AND o.status<>'cancelled'
    GROUP BY oi.product_id,oi.product_name ORDER BY quantity DESC,sales DESC LIMIT 10");
$productsQ->execute([$rid,$fromDate,$toExclusive]);
$products=array_map(static fn($row)=>['name'=>$row['name'],'quantity'=>(int)$row['quantity'],'sales'=>(float)$row['sales']],$productsQ->fetchAll());

json_out(['ok'=>true,'range'=>['from'=>$fromDate,'to'=>$toRaw],'summary'=>$summary,'daily'=>array_values($daily),
    'order_types'=>$typeQ->fetchAll(),'payments'=>$paymentQ->fetchAll(),'statuses'=>$statusQ->fetchAll(),'top_products'=>$products]);
