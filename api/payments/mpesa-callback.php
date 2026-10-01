<?php
// Daraja STK Push callback. The unpredictable query key is checked before any payment row is touched.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/mpesa.php';
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !mpesa_is_configured()) json_error('Callback unavailable.', 404);
$sentKey = (string)($_GET['key'] ?? '');
if ($sentKey === '' || !hash_equals(MPESA_CALLBACK_TOKEN, $sentKey)) json_error('Not found.', 404);
$body = json_decode((string)file_get_contents('php://input'), true);
$callback = $body['Body']['stkCallback'] ?? null;
if (!is_array($callback) || empty($callback['CheckoutRequestID'])) json_error('Invalid callback.', 400);

$metadata = [];
foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
    if (isset($item['Name'])) $metadata[(string)$item['Name']] = $item['Value'] ?? null;
}
$pdo = db();
try {
    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT p.id,p.order_id,p.amount,p.status,p.mpesa_phone,p.merchant_request_id,o.customer_user_id,o.order_number
        FROM payments p JOIN orders o ON o.id=p.order_id
        WHERE p.checkout_request_id=? AND p.method=\'mpesa\' FOR UPDATE');
    $q->execute([(string)$callback['CheckoutRequestID']]); $payment = $q->fetch();
    if (!$payment) { $pdo->rollBack(); http_response_code(200); echo '{"ResultCode":0,"ResultDesc":"Accepted"}'; exit; }
    if (!empty($payment['merchant_request_id']) && !hash_equals((string)$payment['merchant_request_id'],(string)($callback['MerchantRequestID'] ?? ''))) {
        $pdo->rollBack(); http_response_code(200); echo '{"ResultCode":0,"ResultDesc":"Accepted"}'; exit;
    }
    if (in_array($payment['status'],['completed','failed'],true)) { $pdo->commit(); http_response_code(200); echo '{"ResultCode":0,"ResultDesc":"Already processed"}'; exit; }

    $success = (string)($callback['ResultCode'] ?? '') === '0';
    if ($success) {
        $receipt = clean_str($metadata['MpesaReceiptNumber'] ?? '', 40);
        $paidAmount = $metadata['Amount'] ?? null;
        $paidPhone = isset($metadata['PhoneNumber']) ? mpesa_normalize_phone((string)$metadata['PhoneNumber']) : null;
        $expectedPhone = $payment['mpesa_phone'] ? mpesa_normalize_phone((string)$payment['mpesa_phone']) : null;
        if (!$receipt || !is_numeric($paidAmount) || abs((float)$paidAmount - (float)$payment['amount']) > 0.01
            || (array_key_exists('PhoneNumber',$metadata) && !$paidPhone)
            || ($expectedPhone && $paidPhone && $expectedPhone !== $paidPhone)) {
            $pdo->rollBack(); error_log('Daraja callback mismatch for checkout ' . (string)$callback['CheckoutRequestID']);
            http_response_code(200); echo '{"ResultCode":0,"ResultDesc":"Accepted"}'; exit;
        }
        $pdo->prepare('UPDATE payments SET status=\'completed\',mpesa_receipt=?,mpesa_phone=?,callback_received_at=NOW(),paid_at=NOW(),notes=\'M-Pesa payment confirmed\' WHERE id=?')
            ->execute([$receipt,$paidPhone ?: $expectedPhone,(int)$payment['id']]);
        $pdo->prepare('UPDATE orders SET payment_status=\'paid\',payment_method=\'mpesa\' WHERE id=?')
            ->execute([(int)$payment['order_id']]);
        if ($payment['customer_user_id']) {
            $pdo->prepare('INSERT IGNORE INTO customer_notifications (user_id,order_id,status,title,message) VALUES (?,? ,\'payment_confirmed\',\'Payment confirmed\',?)')
                ->execute([(int)$payment['customer_user_id'],(int)$payment['order_id'],'M-Pesa payment received for order #' . $payment['order_number'] . '.']);
        }
    } else {
        $reason = clean_str($callback['ResultDesc'] ?? 'M-Pesa payment was not completed.', 200);
        $pdo->prepare('UPDATE payments SET status=\'failed\',callback_received_at=NOW(),notes=? WHERE id=?')
            ->execute([$reason,(int)$payment['id']]);
        $pdo->prepare('UPDATE orders SET payment_status=\'failed\' WHERE id=? AND payment_status<>\'paid\'')
            ->execute([(int)$payment['order_id']]);
        refund_mpesa_order_loyalty($pdo,(int)$payment['order_id']);
        if ($payment['customer_user_id']) {
            $pdo->prepare('INSERT IGNORE INTO customer_notifications (user_id,order_id,status,title,message) VALUES (?,? ,\'payment_failed\',\'Payment not completed\',?)')
                ->execute([(int)$payment['customer_user_id'],(int)$payment['order_id'],'M-Pesa payment for order #' . $payment['order_number'] . ' was not completed. You can ask the restaurant for help.']);
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Daraja callback processing failed: ' . $e->getMessage());
    http_response_code(500); echo '{"ResultCode":1,"ResultDesc":"Temporary processing error"}'; exit;
}
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo '{"ResultCode":0,"ResultDesc":"Accepted"}';
