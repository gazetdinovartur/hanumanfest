<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );

function make_signature(array $params, string $secret_key): string {
    $filtered = [];
    foreach ($params as $k => $v) {
        if ($k === 'signature') continue;
        if ($v === '' || $v === null) continue;
        $filtered[strtolower($k)] = (string)$v;
    }

    ksort($filtered, SORT_STRING);

    $pairs = [];
    foreach ($filtered as $k => $v) {
        $pairs[] = $k . '=' . base64_encode($v);
    }

    $data = implode('&', $pairs);

    return sha1($secret_key . sha1($secret_key . $data));
}

$raw = file_get_contents('php://input');
$input = $raw ? json_decode($raw, true) : null;

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

$amountRaw = isset($input['amount']) ? $input['amount'] : null;
$currency = MB_DEFAULT_CURRENCY;

if ($amountRaw === null) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_amount']);
    exit;
}

$amountFloat = round((float)$amountRaw, 2);
if ($amountFloat <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_amount']);
    exit;
}

$amountStr = number_format($amountFloat, 2, '.', '');

$host = idn_to_ascii($_SERVER['HTTP_HOST']);
$return_page = 'https://' . $host . MB_SUCCESS_PATH;

$order_id = (string) random_int(10000000, 99999999);
$success_url = 'https://' . $host . '/' . MB_SUCCESS_PATH;
$fail_url    = 'https://' . $host . '/' . MB_FAIL_PATH;

$items = [
    [
        'name'           => 'Оплата участия в Хануман Фест',
        'payment_method' => 'full_prepayment',
        'payment_object' => 'service',
        'sno'            => 'patent',
        'price'          => $amountFloat,
        'quantity'       => 1,
        'discount_sum'   => 0,
        'vat'            => 'none'
    ]
];

$total = 0.0;
foreach ($items as &$item) {
    $lineSum = ($item['price'] * $item['quantity']) - ($item['discount_sum'] ?? 0);
    $lineSum = round($lineSum, 2, PHP_ROUND_HALF_UP);
    $item['sum'] = number_format($lineSum, 2, '.', '');
    $item['price'] = number_format($item['price'], 2, '.', '');
    $item['quantity'] = (string)$item['quantity'];
    $item['discount_sum'] = number_format($item['discount_sum'], 2, '.', '');
    $total += $lineSum;
}
unset($item);

$total = round($total, 2, PHP_ROUND_HALF_UP);
if (abs($total - $amountFloat) > 0.01) {
    $diff = $amountFloat - $total;
    $lastIndex = count($items) - 1;
    $items[$lastIndex]['sum'] = number_format(
        ((float)$items[$lastIndex]['sum'] + $diff),
        2,
        '.',
        ''
    );
}

$receipt_items_json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$params = [
    'merchant'       => MB_MERCHANT,
    'order_id'       => $order_id,
    'amount'         => $amountStr,
    'description'    => $description ?? "Оплата участия {$order_id}",
    'success_url'    => $success_url,
    'fail_url'       => $fail_url,
    'unix_timestamp' => (string) time(),
    'receipt_items'  => $receipt_items_json,
    'client_phone'   => $input['phone'],
    'client_email'   => $input['email'],
];

$params['signature'] = make_signature($params, MB_SECRET_KEY);

echo json_encode([
    'gateway_url' => MB_GATEWAY_URL,
    'params'      => $params,
    'order_id'    => $order_id
], JSON_UNESCAPED_SLASHES);

exit;