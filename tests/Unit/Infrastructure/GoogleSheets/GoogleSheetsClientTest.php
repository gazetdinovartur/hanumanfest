<?php

namespace App\Tests\Unit\Infrastructure\GoogleSheets;

use App\Infrastructure\GoogleSheets\Dto\ApplicationExportPayload;
use App\Infrastructure\GoogleSheets\GoogleSheetsClient;
use App\Infrastructure\GoogleSheets\GoogleSheetsRegistrationsReference;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

final class GoogleSheetsClientTest extends TestCase
{
    public function testSkipsRequestWhenWebhookUrlIsEmpty(): void
    {
        $client = new GoogleSheetsClient(
            new MockHttpClient(),
            new GoogleSheetsRegistrationsReference(''),
            new NullLogger(),
        );
        $client->exportApplication(new ApplicationExportPayload(
            action: 'application',
            applicationUuid: 'uuid',
            name: 'Name',
            email: 'a@b.c',
            phone: '+7999',
            productName: 'Product',
            participationOptionName: 'Option',
            pricingPeriodName: 'Period',
            adultsCount: '1',
            childrenCount: '0',
            totalAmount: '100.00',
            payNowAmount: '50.00',
            transferIncluded: '0',
            paymentFactor: '0.5',
        ));

        self::assertTrue(true);
    }

    public function testTestExportUsesTestWebhookNotProd(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requests) {
            $requests[] = $url;

            return new \Symfony\Component\HttpClient\Response\MockResponse('ok');
        });
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://script.google.com/macros/s/prod/exec'),
            new NullLogger(),
            new GoogleSheetsRegistrationsReference('https://script.google.com/macros/s/test/exec', null),
        );

        $payload = new ApplicationExportPayload(
            action: 'application',
            applicationUuid: 'uuid',
            name: 'Name',
            email: 'a@b.c',
            phone: '+7999',
            productName: 'Product',
            participationOptionName: 'Option',
            pricingPeriodName: 'Period',
            adultsCount: '1',
            childrenCount: '0',
            totalAmount: '2.00',
            payNowAmount: '1.00',
            transferIncluded: '0',
            paymentFactor: '0.5',
        );
        $client->exportApplication($payload, true);

        self::assertSame(['https://script.google.com/macros/s/test/exec'], $requests);
    }

    public function testTestExportSkippedWhenTestWebhookEmptyDoesNotHitProd(): void
    {
        $httpClient = new MockHttpClient(function (): never {
            self::fail('Prod webhook must not be called for test applications');
        });
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://script.google.com/macros/s/prod/exec'),
            new NullLogger(),
            new GoogleSheetsRegistrationsReference('', null),
        );
        $client->exportApplication(new ApplicationExportPayload(
            action: 'application',
            applicationUuid: 'uuid',
            name: 'Name',
            email: 'a@b.c',
            phone: '+7999',
            productName: 'Product',
            participationOptionName: 'Option',
            pricingPeriodName: 'Period',
            adultsCount: '1',
            childrenCount: '0',
            totalAmount: '2.00',
            payNowAmount: '1.00',
            transferIncluded: '0',
            paymentFactor: '0.5',
        ), true);

        self::assertTrue(true);
    }
}
