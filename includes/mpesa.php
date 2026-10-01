<?php
require_once __DIR__ . '/../config/mpesa.php';

class MpesaTransportException extends RuntimeException {}

function mpesa_is_configured(): bool {
    $query=[]; parse_str((string)(parse_url(MPESA_CALLBACK_URL, PHP_URL_QUERY) ?? ''),$query);
    $callbackKey=(string)($query['key'] ?? '');
    $callbackScheme=(string)(parse_url(MPESA_CALLBACK_URL,PHP_URL_SCHEME) ?? '');
    return MPESA_CONSUMER_KEY !== '' && MPESA_CONSUMER_SECRET !== '' && MPESA_SHORTCODE !== ''
        && MPESA_PASSKEY !== '' && MPESA_CALLBACK_TOKEN !== 'replace-with-a-long-random-secret'
        && filter_var(MPESA_CALLBACK_URL, FILTER_VALIDATE_URL) && $callbackKey !== ''
        && hash_equals(MPESA_CALLBACK_TOKEN,$callbackKey)
        && (MPESA_ENV !== 'production' || $callbackScheme === 'https')
        && in_array(MPESA_ENV, ['sandbox','production'], true)
        && in_array(MPESA_TRANSACTION_TYPE, ['CustomerPayBillOnline','CustomerBuyGoodsOnline'], true);
}

function mpesa_base_url(): string {
    return MPESA_ENV === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
}

function mpesa_request(string $url, array $headers, ?array $body = null): array {
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for M-Pesa requests.');
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    ];
    if ($body !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch); curl_close($ch);
    if ($raw === false || $error !== '') throw new MpesaTransportException('Could not verify the response from the M-Pesa service.');
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        if ($body !== null && $status >= 200 && $status < 300) throw new MpesaTransportException('Could not verify the M-Pesa response.');
        throw new RuntimeException('The M-Pesa service returned an unreadable response.');
    }
    if ($status < 200 || $status >= 300) {
        $message = clean_str($data['errorMessage'] ?? $data['ResponseDescription'] ?? 'M-Pesa request rejected.', 180);
        throw new RuntimeException($message);
    }
    return $data;
}

function mpesa_access_token(): string {
    $basic = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    $data = mpesa_request(mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials',
        ['Authorization: Basic ' . $basic, 'Accept: application/json']);
    if (empty($data['access_token'])) throw new RuntimeException('Daraja did not return an access token.');
    return (string)$data['access_token'];
}

function mpesa_normalize_phone(string $phone): ?string {
    $digits = preg_replace('/\D+/', '', $phone);
    if (strlen($digits) === 10 && $digits[0] === '0') $digits = '254' . substr($digits, 1);
    elseif (strlen($digits) === 9 && ($digits[0] === '7' || $digits[0] === '1')) $digits = '254' . $digits;
    return preg_match('/^254[17][0-9]{8}$/', $digits) ? $digits : null;
}

function mpesa_start_stk(string $phone, int $amount, string $accountReference, string $description): array {
    if (!mpesa_is_configured()) throw new RuntimeException('M-Pesa sandbox settings are incomplete.');
    if ($amount < 1) throw new RuntimeException('The M-Pesa amount must be at least KSh 1.');
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE, 'Password' => $password, 'Timestamp' => $timestamp,
        'TransactionType' => MPESA_TRANSACTION_TYPE, 'Amount' => $amount,
        'PartyA' => $phone, 'PartyB' => MPESA_SHORTCODE, 'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL, 'AccountReference' => substr($accountReference, 0, 12),
        'TransactionDesc' => substr($description, 0, 30),
    ];
    return mpesa_request(mpesa_base_url() . '/mpesa/stkpush/v1/processrequest',
        ['Authorization: Bearer ' . mpesa_access_token(), 'Content-Type: application/json', 'Accept: application/json'], $payload);
}
