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
                return new MockResponse('{"sheets":[{"properties":{"title":"Регистрации"}}]}');
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
            'email',
            'adultsCount',
            'childrenCount',
            'paidTotal',
            'remaining',
            'participationOptionName',
            'transferIncluded',
            'notes',
            'applicationUuid',
            'payment1Amount',
            'payment1Date',
            'payment1Id',
            'payment2Amount',
            'payment2Date',
            'payment2Id',
            'payNowAmount',
            'totalAmount',
            'paymentFactor',
            'pricingPeriodName',
        ]);
        self::assertCount(\count(RegistrationSheetRow::HEADERS), RegistrationSheetRow::RUSSIAN_HEADERS);
        self::assertSame('ФИО', RegistrationSheetRow::RUSSIAN_HEADERS[0]);
        self::assertSame('Осталось оплатить', RegistrationSheetRow::RUSSIAN_HEADERS[6]);
        self::assertSame('Id заявки', RegistrationSheetRow::RUSSIAN_HEADERS[10]);
        self::assertSame('Ценовой период', RegistrationSheetRow::RUSSIAN_HEADERS[20]);
        self::assertSame('Export Test', $captured[0]);
        self::assertSame('+79160000005', $captured[1]);
        self::assertSame('export@test.example', $captured[2]);
        self::assertSame('1', $captured[3]);
        self::assertSame('0', $captured[4]);
        self::assertSame('0.00', $captured[5]);
        self::assertSame('3600.00', $captured[6]);
        self::assertSame('Option', $captured[7]);
        self::assertSame('0', $captured[8]);
        self::assertSame('С другом Иваном', $captured[9]);
        self::assertSame('1800.00', $captured[17]);
        self::assertSame('3600.00', $captured[18]);
        self::assertSame('0.5', $captured[19]);
        self::assertSame('Period', $captured[20]);
        self::assertNotContains('Hanuman Fest 2026', $captured);
    }

    public function testExportPaymentFillsFirstPaymentSlot(): void
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
            payNowAmount: '1800.00',
            participationOptionName: 'Option',
            transferIncluded: '0',
            paymentFactor: '0.5',
            notes: 'С другом Иваном',
            payment1Amount: '',
            payment1Date: '',
            payment1Id: '',
            payment2Amount: '',
            payment2Date: '',
            payment2Id: '',
            paidTotal: '0.00',
            remaining: '3600.00',
            pricingPeriodName: 'Period',
            applicationUuid: $uuid,
        ))->valuesFor(RegistrationSheetRow::HEADERS);
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$updated, $uuid, $existingRow): MockResponse {
            if ($method === 'GET' && !str_contains($url, '/values/')) {
                return new MockResponse('{"sheets":[{"properties":{"title":"Регистрации"}}]}');
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

        self::assertSame('1800.00', $updated[11]);
        self::assertSame('yk-1', $updated[13]);
        self::assertSame('1800.00', $updated[5]);
        self::assertSame('1800.00', $updated[6]);
        self::assertSame('1800.00', $updated[17]);
        self::assertSame('3600.00', $updated[18]);
        self::assertSame('С другом Иваном', $updated[9]);
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
