<?php

namespace App\Infrastructure\Yookassa;

use App\Infrastructure\Yookassa\Dto\CreatePaymentResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Портировано из legacy/wordpress/yookassa-plugin.php (yk_create_payment, yk_webhook verify).
 */
class YookassaClient
{
    private const API_BASE = 'https://api.yookassa.ru/v3';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $shopId,
        private readonly string $secretKey,
        private readonly string $returnUrl,
    ) {
    }

    public function createPayment(string $email, string $phone, int $amountRub, string $description = 'Оплата участия'): CreatePaymentResult
    {
        $amountFormatted = $this->formatAmount($amountRub);

        $payload = [
            'amount' => [
                'value' => $amountFormatted,
                'currency' => 'RUB',
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $this->returnUrl,
            ],
            'capture' => true,
            'description' => $description,
            'receipt' => [
                'customer' => [
                    'email' => $email,
                ],
                'items' => [
                    [
                        'description' => $description,
                        'quantity' => '1.00',
                        'amount' => [
                            'value' => $amountFormatted,
                            'currency' => 'RUB',
                        ],
                        'vat_code' => 6,
                    ],
                ],
            ],
        ];

        $response = $this->httpClient->request('POST', self::API_BASE.'/payments', [
            'headers' => $this->authHeaders([
                'Idempotence-Key' => Uuid::v4()->toRfc4122(),
            ]),
            'json' => $payload,
            'timeout' => 20,
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \RuntimeException('YooKassa payment creation failed: '.$response->getContent(false));
        }

        $body = $response->toArray();

        if (!isset($body['id'], $body['confirmation']['confirmation_url'])) {
            throw new \RuntimeException('Invalid YooKassa API response');
        }

        return new CreatePaymentResult(
            paymentId: $body['id'],
            gatewayUrl: $body['confirmation']['confirmation_url'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyPayment(string $paymentId): array
    {
        $response = $this->httpClient->request('GET', self::API_BASE.'/payments/'.$paymentId, [
            'headers' => $this->authHeaders(),
            'timeout' => 20,
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new \RuntimeException('YooKassa payment verification failed' . $response->getContent());
        }

        $body = $response->toArray();

        if (!isset($body['status'])) {
            throw new \RuntimeException('Invalid YooKassa verification response');
        }

        return $body;
    }

  /**
   * @param array<string, string> $extra
   * @return array<string, string>
   */
    private function authHeaders(array $extra = []): array
    {
        return array_merge([
            'Authorization' => 'Basic '.base64_encode($this->shopId.':'.$this->secretKey),
            'Content-Type' => 'application/json',
        ], $extra);
    }

    private function formatAmount(int $amountRub): string
    {
        return number_format($amountRub, 2, '.', '');
    }
}
