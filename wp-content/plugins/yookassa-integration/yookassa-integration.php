<?php
/*
Plugin Name: YooKassa Integration
Description: Secure YooKassa integration with webhook verification and DB storage
Version: 1.1
*/

if (!defined('ABSPATH')) exit;

/* =========================
   1. CREATE TABLE
========================= */

register_activation_hook(__FILE__, function () {
    global $wpdb;

    $table = $wpdb->prefix . 'yookassa_payments';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        payment_id VARCHAR(64) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(32) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(32) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY payment_id (payment_id),
        KEY status (status)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

/* =========================
   2. CREATE PAYMENT
========================= */

add_action('rest_api_init', function () {
    register_rest_route('yk/v1', '/create', [
        'methods' => 'POST',
        'callback' => 'yk_create_payment',
        'permission_callback' => '__return_true'
    ]);
});

function yk_create_payment(WP_REST_Request $request) {
    global $wpdb;

    if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
        return new WP_REST_Response(['error' => 'Nonce failed'], 403);
    }

    $manual = max(0, (float)($request['manual'] ?? 0));
    $amount = max(0, (float)($request['amount'] ?? 0));
    $paymentAmount = $manual > 0 ? $manual : $amount;
    $amount = number_format($paymentAmount, 2, '.', '');
    $email  = sanitize_email($request['email']);
    $phone  = sanitize_text_field($request['phone']);

    if (!$amount || !$email || !$phone) {
        return new WP_REST_Response(['error' => 'Invalid input'], 400);
    }

    $payload = [
        "amount" => [
            "value" => $amount,
            "currency" => "RUB"
        ],
        "confirmation" => [
            "type" => "redirect",
            "return_url" => home_url('/')
        ],
        "capture" => true,
        "description" => "Оплата участия",
        "receipt" => [
            "customer" => [
                "email" => $email
            ],
            "items" => [
                [
                    "description" => "Оплата участия",
                    "quantity" => "1.00",
                    "amount" => [
                        "value" => $amount,
                        "currency" => "RUB"
                    ],
                    "vat_code" => 6
                ]
            ]
        ]
    ];

    $response = wp_remote_post('https://api.yookassa.ru/v3/payments', [
        'headers' => [
            'Content-Type'   => 'application/json',
            'Idempotence-Key'=> wp_generate_uuid4(),
            'Authorization'  => 'Basic ' . base64_encode(YOOKASSA_SHOP_ID . ':' . YOOKASSA_SECRET_KEY)
        ],
        'body'    => json_encode($payload),
        'timeout' => 20
    ]);

    if (is_wp_error($response)) {
        return new WP_REST_Response(['error' => 'Payment API error'], 500);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    if ($response_code !== 200) {
        return new WP_REST_Response([
            'error' => 'Payment API error',
            'code'  => $response_code,
            'body'  => json_decode($response_body, true)
        ], 500);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['id'], $body['confirmation']['confirmation_url'])) {
        return new WP_REST_Response(['error' => 'Invalid API response'], 500);
    }

    $table = $wpdb->prefix . 'yookassa_payments';

    $wpdb->insert($table, [
        'payment_id' => $body['id'],
        'email'      => $email,
        'phone'      => $phone,
        'amount'     => $amount,
        'status'     => 'pending'
    ]);

    return [
        'payment_id' => $body['id'],
        'gateway_url'=> $body['confirmation']['confirmation_url']
    ];
}

/* =========================
   3. SECURE WEBHOOK
========================= */

add_action('rest_api_init', function () {
    register_rest_route('yk/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'yk_webhook',
        'permission_callback' => '__return_true'
    ]);
});

function yk_webhook(WP_REST_Request $request) {
    global $wpdb;
    $data = $request->get_json_params();

    if (!isset($data['object']['id'])) {
        return new WP_REST_Response(['error' => 'Bad request'], 400);
    }

    $paymentId = sanitize_text_field($data['object']['id']);

    // 🔒 Проверяем реальный статус через YooKassa API
    $verify = wp_remote_get("https://api.yookassa.ru/v3/payments/$paymentId", [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(YOOKASSA_SHOP_ID . ':' . YOOKASSA_SECRET_KEY)
        ],
        'timeout' => 20
    ]);

    if (is_wp_error($verify) || wp_remote_retrieve_response_code($verify) !== 200) {
        return new WP_REST_Response(['error' => 'Verification failed'], 500);
    }

    $verifyBody = json_decode(wp_remote_retrieve_body($verify), true);

    if (!isset($verifyBody['status'])) {
        return new WP_REST_Response(['error' => 'Invalid verification response'], 500);
    }

    $status = sanitize_text_field($verifyBody['status']);

    $table = $wpdb->prefix . 'yookassa_payments';

    $payment = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE payment_id = %s", $paymentId),
        ARRAY_A
    );

    if (!$payment) {
        return new WP_REST_Response(['error' => 'Payment not found'], 404);
    }

    if ($status === 'succeeded') {

        // обновляем статус
        $wpdb->update(
            $table,
            ['status' => 'succeeded'],
            ['payment_id' => $paymentId]
        );

        // 2️⃣ Отправляем в Google Sheets
        $response = wp_remote_post(
            home_url('/google-proxy.php'),
            [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'email'     => (string)$payment['email'],
                    'phone'     => (string)$payment['phone'],
                    'amount'    => number_format((float)$payment['amount'], 2, '.', ''),
                    'currency'  => 'RUB',
                    'paymentId' => (string)$payment['payment_id'],
                    'paidAt'   => gmdate('c', strtotime($payment['updated_at']))
                ]),
                'timeout' => 20
            ]
        );

        if (is_wp_error($response)) {
            error_log('Google error: ' . $response->get_error_message());
        } else {
            error_log('Google response: ' . wp_remote_retrieve_body($response));
        }
    }

    if ($status === 'canceled') {
        $wpdb->update(
            $table,
            ['status' => 'canceled'],
            ['payment_id' => $paymentId]
        );
    }

    return new WP_REST_Response(['ok' => true], 200);
}

/* =========================
   4. CHECK STATUS
========================= */

add_action('rest_api_init', function () {
    register_rest_route('yk/v1', '/check/(?P<id>[a-zA-Z0-9-]+)', [
        'methods' => 'GET',
        'callback' => 'yk_check',
        'permission_callback' => '__return_true'
    ]);
});

function yk_check($request) {
    global $wpdb;

    $table = $wpdb->prefix . 'yookassa_payments';

    $payment = $wpdb->get_row(
        $wpdb->prepare("SELECT status, amount FROM $table WHERE payment_id = %s", $request['id'])
    );

    if (!$payment) {
        return ['paid' => false];
    }

    return [
        'paid'   => $payment->status === 'succeeded',
        'status' => $payment->status,
        'amount' => $payment->amount,
        'email' => $payment->email,
        'phone' => $payment->phone,
        'payment_id' => $payment->payment_id,
        'updated_at' => $payment->updated_at,
    ];
}

add_action('rest_api_init', function () {
    register_rest_route('yk/v1', '/sync', [
        'methods' => 'GET',
        'callback' => 'sync_payments',
        'permission_callback' => '__return_true'
    ]);
});

function sync_payments() {
    global $wpdb;

    $table = $wpdb->prefix . 'yookassa_payments';

    $payments = $wpdb->get_results("
        SELECT * FROM $table
        WHERE status = 'succeeded'
    ", ARRAY_A);

    foreach ($payments as $payment) {

        $response = wp_remote_post(
            home_url('/google-proxy.php'),
            [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'email'     => (string)$payment['email'],
                    'phone'     => (string)$payment['phone'],
                    'amount'    => number_format((float)$payment['amount'], 2, '.', ''),
                    'currency'  => 'RUB',
                    'paymentId' => (string)$payment['payment_id'],
                    'paidAt'   => gmdate('c', strtotime($payment['updated_at']))
                ]),
                'timeout' => 20
            ]
        );

        print_r($response);
    }
    return;
}