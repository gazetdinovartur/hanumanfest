<?php

namespace App\Tests\Unit\Infrastructure\GoogleSheets;

use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Infrastructure\GoogleSheets\Dto\RegistrationSheetRow;
use App\Infrastructure\GoogleSheets\GoogleSheetsClient;
use App\Infrastructure\GoogleSheets\GoogleSheetsExportService;
use App\Infrastructure\GoogleSheets\GoogleSheetsRegistrationsReference;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleSheetsExportServiceTest extends TestCase
{
    public function testExportApplicationWritesCanonicalColumnOrderWithoutProductName(): void
    {
        $captured = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            if ($method === 'GET' && !str_contains($url, '/values/')) {
                return new MockResponse('{"sheets":[{"properties":{"sheetId":0,"title":"Регистрации"}}]}');
            }
            if ($method === 'GET') {
                return new MockResponse('{}');
            }
            if ($method === 'POST' && str_contains($url, ':append')) {
                $captured = json_decode($options['body'] ?? '{}', true)['values'][0] ?? null;
            }

            return new MockResponse('{}');
        });
        $service = $this->service($httpClient);
        $service->exportApplication($this->application());

        self::assertSame(RegistrationSheetRow::HEADERS, [
            'name',
            'phone',
            'participationOptionName',
            'adultsCount',
            'childrenCount',
            'paidTotal',
            'remaining',
            'transferIncluded',
            'notes',
            'totalAmount',
            'pricingPeriodName',
            'payments',
            'email',
            'applicationUuid',
        ]);
        self::assertCount(\count(RegistrationSheetRow::HEADERS), RegistrationSheetRow::RUSSIAN_HEADERS);
        self::assertSame('ФИО', RegistrationSheetRow::RUSSIAN_HEADERS[0]);
        self::assertSame('Вариант участия', RegistrationSheetRow::RUSSIAN_HEADERS[2]);
        self::assertSame('Платежи', RegistrationSheetRow::RUSSIAN_HEADERS[11]);
        self::assertSame('Почта', RegistrationSheetRow::RUSSIAN_HEADERS[12]);
        self::assertSame('Id заявки', RegistrationSheetRow::RUSSIAN_HEADERS[13]);
        self::assertSame('Export Test', $captured[0]);
        self::assertSame('+79160000005', $captured[1]);
        self::assertSame('Option', $captured[2]);
        self::assertSame('1', $captured[3]);
        self::assertSame('0', $captured[4]);
        self::assertSame('0.00', $captured[5]);
        self::assertSame('3600.00', $captured[6]);
        self::assertSame('нет', $captured[7]);
        self::assertSame('С другом Иваном', $captured[8]);
        self::assertSame('3600.00', $captured[9]);
        self::assertSame('Period', $captured[10]);
        self::assertSame('', $captured[11]);
        self::assertSame('export@test.example', $captured[12]);
        self::assertNotContains('Hanuman Fest 2026', $captured);
        self::assertNotContains('1800.00', $captured);
        self::assertNotContains('0.5', $captured);
        self::assertNotContains('payNowAmount', RegistrationSheetRow::HEADERS);
        self::assertNotContains('paymentFactor', RegistrationSheetRow::HEADERS);
        self::assertNotContains('payment1Amount', RegistrationSheetRow::HEADERS);
    }

    public function testExportPaymentFillsPaymentsColumn(): void
    {
        $application = $this->application();
        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId('yk-1');
        $payment->setAmount(1800);
        $payment->setStatus(PaymentStatus::Succeeded);
        $payment->setPaidAt(new \DateTimeImmutable('2026-06-01 12:00:00', new \DateTimeZone('UTC')));
        $application->setPaidAmount(1800);
        $application->addPayment($payment);

        $updated = null;
        $uuid = (string) $application->getUuid();
        $existingRow = (new RegistrationSheetRow(
            name: 'Export Test',
            phone: '+79160000005',
            email: 'export@test.example',
            adultsCount: '1',
            childrenCount: '0',
            totalAmount: '3600.00',
            participationOptionName: 'Option',
            transferIncluded: 'нет',
            notes: 'С другом Иваном',
            payments: '',
            paidTotal: '0.00',
            remaining: '3600.00',
            pricingPeriodName: 'Period',
            applicationUuid: $uuid,
        ))->valuesFor(RegistrationSheetRow::HEADERS);
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$updated, $uuid, $existingRow): MockResponse {
            if ($method === 'GET' && !str_contains($url, '/values/')) {
                return new MockResponse('{"sheets":[{"properties":{"sheetId":0,"title":"Регистрации"}}]}');
            }
            if ($method === 'GET') {
                $decoded = rawurldecode($url);
                if (str_contains($decoded, '1:2')) {
                    return new MockResponse(json_encode([
                        'values' => [
                            RegistrationSheetRow::HEADERS,
                            RegistrationSheetRow::RUSSIAN_HEADERS,
                        ],
                    ], JSON_THROW_ON_ERROR));
                }
                if (preg_match("/![A-Z]+:[A-Z]+/", str_replace("'", '', $decoded))) {
                    return new MockResponse(json_encode([
                        'values' => [
                            ['applicationUuid'],
                            ['Id заявки'],
                            [$uuid],
                        ],
                    ], JSON_THROW_ON_ERROR));
                }

                return new MockResponse(json_encode([
                    'values' => [$existingRow],
                ], JSON_THROW_ON_ERROR));
            }
            if ($method === 'PUT') {
                $updated = json_decode($options['body'] ?? '{}', true)['values'][0] ?? null;
            }

            return new MockResponse('{}');
        });

        $this->service($httpClient)->exportSuccessfulPayment($payment);

        self::assertSame("1800.00 ₽ · 01.06.2026 15:00:00 · yk-1", $updated[11]);
        self::assertSame('1800.00', $updated[5]);
        self::assertSame('1800.00', $updated[6]);
        self::assertSame('3600.00', $updated[9]);
        self::assertSame('С другом Иваном', $updated[8]);
        self::assertSame('нет', $updated[7]);
    }

    private function service(MockHttpClient $httpClient): GoogleSheetsExportService
    {
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://docs.google.com/spreadsheets/d/prod-sheet/edit'),
            accessToken: 'tok',
        );

        return new GoogleSheetsExportService($client);
    }

    private function application(): Application
    {
        $user = new User();
        $user->setName('Export Test');
        $user->setEmail('export@test.example');
        $user->setPhone('+79160000005');

        $application = new Application();
        $application->setUser($user);
        $application->setPayload([
            'participationOptionName' => 'Option',
            'pricingPeriodName' => 'Period',
            'payNowAmount' => 1800,
            'adultsCount' => 1,
            'childrenCount' => 0,
            'transferIncluded' => false,
            'paymentFactor' => 0.5,
            'tentRoommate' => 'С другом Иваном',
        ]);
        $application->setTotalAmount(3600);

        return $application;
    }
}
