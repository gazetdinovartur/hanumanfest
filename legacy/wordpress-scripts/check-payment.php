<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

function make_signature(array $params, string $secret_key): string {
    $filtered = [];
    foreach ($params as $k => $v) {
        if ($k === 'signature') continue;
        $s = (string)$v;
        if ($s === '') continue;
        $filtered[$k] = $s;
    }

    ksort($filtered, SORT_STRING);

    $chunks = [];
    foreach ($filtered as $k => $v) {
        $chunks[] = $k . '=' . base64_encode($v);
    }

    $data = implode('&', $chunks);
    $inner = sha1($secret_key . $data);
    $sig = sha1($secret_key . $inner);

    return $sig;
}

$transaction_id = $_GET['transaction_id'] ?? null;

if (!$transaction_id) {
    http_response_code(400);
    echo json_encode(['error' => 'transaction_id missing']);
    exit;
}

$params = [
    'merchant' => MB_MERCHANT,
    'transaction_id' => $transaction_id,
    'unix_timestamp' => (string) time(),
];

$params['signature'] = make_signature($params, MB_SECRET_KEY);

$url = rtrim(MB_API_TRANSACTION_URL, '/') . '?' . http_build_query($params);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    http_response_code(502);
    echo json_encode([
        'error' => 'gateway_error',
        'http_code' => $httpCode,
        'curl_error' => $curlErr,
        'raw' => $response
    ]);
    exit;
}

$decoded = json_decode($response, true);
if ($decoded === null) {
    echo json_encode([
        'error' => 'invalid_response',
        'raw' => $response
    ]);
    exit;
}

$tx = $decoded['transaction'] ?? [];
$status = $tx['state'] ?? null;
$amount = $tx['amount'] ?? null;
$currency = $tx['currency'] ?? null;
$email = $tx['client_email'] ?? null;
$phone = $tx['client_phone'] ?? null;
$completed_datetime = $tx['completed_datetime'] ?? null;
$transaction_id = $tx['transaction_id'] ?? $transaction_id;

$paid = in_array(strtoupper($status), ['COMPLETE', 'SUCCESS', 'PAID'], true);

echo json_encode([
    'transaction_id' => $transaction_id,
    'payment_status_raw' => $status,
    'paid' => $paid ? 1 : 0,
    'email' => $email,
    'amount' => $amount,
    'phone' => $phone,
    'currency' => $currency,
    'completed_datetime' => $completed_datetime,
    'raw' => $decoded
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
exit;