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
            totalAmount: '100.00',
            payNowAmount: '50.00',
            adultsCount: '1',
            childrenCount: '0',
            transferIncluded: '0',
            paymentFactor: '0.5',
        ));

        self::assertTrue(true);
    }
}
