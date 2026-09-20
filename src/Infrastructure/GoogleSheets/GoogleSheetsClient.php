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
        private readonly ?GoogleSheetsRegistrationsReference $testRegistrations = null,
    ) {
    }

    public function exportPayment(PaymentExportPayload $payload, bool $isTest = false): void
    {
        $this->send($payload->toArray(), $isTest);
    }

    public function exportApplication(ApplicationExportPayload $payload, bool $isTest = false): void
    {
        $this->send($payload->toArray(), $isTest);
    }

    /** @param array<string, string|null> $data */
    private function send(array $data, bool $isTest): void
    {
        $target = $isTest
            ? ($this->testRegistrations ?? new GoogleSheetsRegistrationsReference('', null))
            : $this->registrations;
        $webhookUrl = $target->webhookUrl();
        $envName = $isTest ? 'REGISTRATION_SHEET_URL_TEST' : 'REGISTRATION_SHEET_URL';

        if ($webhookUrl === '') {
            $this->logger?->warning($envName.' is not an Apps Script webhook, export skipped');

            return;
        }

        $response = $this->httpClient->request('POST', $webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $data,
            'max_redirects' => 5,
            'timeout' => 20,
        ]);

        $this->logger?->info('Google Sheets export response', [
            'action' => $data['action'] ?? 'unknown',
            'target' => $envName,
            'status' => $response->getStatusCode(),
            'body' => $response->getContent(false),
        ]);
    }
}
