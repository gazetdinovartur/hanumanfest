<?php

namespace App\Tests\Unit\Infrastructure\GoogleSheets;

use App\Entity\Application;
use App\Entity\Product;
use App\Entity\User;
use App\Infrastructure\GoogleSheets\Dto\PaymentExportPayload;
use App\Infrastructure\GoogleSheets\GoogleSheetsClient;
use App\Infrastructure\GoogleSheets\GoogleSheetsExportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleSheetsExportServiceTest extends TestCase
{
    public function testPostsJsonToWebhook(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = compact('method', 'url', 'options');

            return new MockResponse('{"ok":true}');
        });

        $client = new GoogleSheetsClient($httpClient, 'https://script.google.com/macros/s/test/exec');
        $client->exportPayment(new PaymentExportPayload(
            email: 'a@b.c',
            phone: '+7999',
            amount: '100.00',
            currency: 'RUB',
            paymentId: 'pay-1',
            paidAt: '2026-01-01T00:00:00+00:00',
            applicationUuid: 'uuid',
            totalAmount: '200.00',
            paidTotal: '100.00',
            remaining: '100.00',
        ));

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
    }

    public function testExportApplicationBuildsPayloadFromEntity(): void
    {
        $captured = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = json_decode($options['body'] ?? '{}', true);

            return new MockResponse('ok');
        });
        $client = new GoogleSheetsClient($httpClient, 'https://example.com/webhook');
        $service = new GoogleSheetsExportService($client);

        $user = new User();
        $user->setName('Export Test');
        $user->setEmail('export@test.example');
        $user->setPhone('+79160000005');

        $product = new Product();
        $product->setName('Hanuman Fest 2026');
        $product->setSlug('hanuman-fest');
        $product->setIsActive(true);

        $application = new Application();
        $application->setUser($user);
        $application->setProduct($product);
        $application->setPayload([
            'participationOptionName' => 'Option',
            'pricingPeriodName' => 'Period',
            'payNowAmount' => 1800,
            'adultsCount' => 1,
            'childrenCount' => 0,
            'transferIncluded' => false,
            'paymentFactor' => 0.5,
        ]);
        $application->setTotalAmount(3600);

        $service->exportApplication($application);

        self::assertSame('application', $captured['action']);
        self::assertSame('export@test.example', $captured['email']);
        self::assertSame('3600.00', $captured['totalAmount']);
    }
}
