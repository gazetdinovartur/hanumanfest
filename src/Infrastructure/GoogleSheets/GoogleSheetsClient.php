<?php

namespace App\Infrastructure\GoogleSheets;

use App\Infrastructure\GoogleSheets\Dto\ApplicationExportPayload;
use App\Infrastructure\GoogleSheets\Dto\PaymentExportPayload;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleSheetsClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GoogleSheetsRegistrationsReference $registrations,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function exportPayment(PaymentExportPayload $payload): void
    {
        $this->send($payload->toArray());
    }

    public function exportApplication(ApplicationExportPayload $payload): void
    {
        $this->send($payload->toArray());
    }

    /** @param array<string, string|null> $data */
    private function send(array $data): void
    {
        if ($this->registrations->webhookUrl() === '') {
            $this->logger?->warning('Google Sheets webhook URL is not configured, export skipped');

            return;
        }

        $response = $this->httpClient->request('POST', $this->registrations->webhookUrl(), [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $data,
            'max_redirects' => 5,
            'timeout' => 20,
        ]);

        $this->logger?->info('Google Sheets export response', [
            'action' => $data['action'] ?? 'unknown',
            'status' => $response->getStatusCode(),
            'body' => $response->getContent(false),
        ]);
    }
}
