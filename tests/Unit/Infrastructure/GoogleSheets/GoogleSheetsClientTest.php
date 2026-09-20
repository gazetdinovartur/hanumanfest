<?php

namespace App\Tests\Unit\Infrastructure\GoogleSheets;

use App\Infrastructure\GoogleSheets\Dto\RegistrationSheetRow;
use App\Infrastructure\GoogleSheets\GoogleSheetsClient;
use App\Infrastructure\GoogleSheets\GoogleSheetsRegistrationsReference;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleSheetsClientTest extends TestCase
{
    public function testSkipsWhenNoCredentials(): void
    {
        $httpClient = new MockHttpClient(function (): never {
            self::fail('Sheets API must not be called without credentials');
        });
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://docs.google.com/spreadsheets/d/prod-sheet/edit'),
            new NullLogger(),
        );
        $client->exportApplication($this->row());
        self::assertTrue(true);
    }

    public function testTestExportUsesTestSpreadsheetNotProd(): void
    {
        $urls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;
            if ($method === 'GET') {
                return new MockResponse('{"values":[["name","applicationUuid"]]}');
            }

            return new MockResponse('{}');
        });
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://docs.google.com/spreadsheets/d/prod-sheet/edit'),
            new NullLogger(),
            new GoogleSheetsRegistrationsReference('https://docs.google.com/spreadsheets/d/test-sheet/edit', null),
            accessToken: 'tok',
        );

        $client->exportApplication($this->row(), true);

        self::assertNotSame([], $urls);
        foreach ($urls as $url) {
            self::assertStringContainsString('/spreadsheets/test-sheet', $url);
            self::assertStringNotContainsString('/spreadsheets/prod-sheet', $url);
        }
    }

    public function testTestExportSkippedWhenTestSheetEmptyDoesNotHitProd(): void
    {
        $httpClient = new MockHttpClient(function (): never {
            self::fail('Prod spreadsheet must not be called for test applications');
        });
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://docs.google.com/spreadsheets/d/prod-sheet/edit'),
            new NullLogger(),
            new GoogleSheetsRegistrationsReference('', null),
            accessToken: 'tok',
        );
        $client->exportApplication($this->row(), true);
        self::assertTrue(true);
    }

    public function testEmptySheetWritesHeadersAndAppendsRow(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'body' => json_decode($options['body'] ?? '{}', true),
            ];
            if ($method === 'GET') {
                return new MockResponse('{}');
            }

            return new MockResponse('{}');
        });
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://docs.google.com/spreadsheets/d/prod-sheet/edit'),
            new NullLogger(),
            accessToken: 'tok',
        );

        $client->exportApplication($this->row());

        self::assertSame('GET', $requests[0]['method']);
        self::assertSame('PUT', $requests[1]['method']);
        self::assertSame(RegistrationSheetRow::HEADERS, $requests[1]['body']['values'][0]);
        self::assertSame('PUT', $requests[2]['method']);
        self::assertSame(RegistrationSheetRow::RUSSIAN_HEADERS, $requests[2]['body']['values'][0]);
        self::assertSame('POST', $requests[3]['method']);
        self::assertStringContainsString(':append', $requests[3]['url']);
        self::assertSame('Ada', $requests[3]['body']['values'][0][0]);
        self::assertSame('uuid-1', $requests[3]['body']['values'][0][10]);
        self::assertNotContains('Hanuman Fest', $requests[3]['body']['values'][0]);
    }

    public function testApplicationExportDoesNotOverwriteExistingUuidRow(): void
    {
        $methods = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$methods): MockResponse {
            $methods[] = $method;
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

                return new MockResponse(json_encode([
                    'values' => [
                        ['applicationUuid'],
                        ['Id заявки'],
                        ['uuid-1'],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            self::fail('Existing application row must not be rewritten');
        });
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://docs.google.com/spreadsheets/d/prod-sheet/edit'),
            new NullLogger(),
            accessToken: 'tok',
        );
        $client->exportApplication($this->row());
        self::assertSame(['GET', 'GET'], $methods);
    }

    public function testEnglishOnlyHeaderRowGetsRussianLabelsWithoutTouchingDataPath(): void
    {
        $bodies = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$bodies): MockResponse {
            if ($method === 'GET') {
                return new MockResponse(json_encode([
                    'values' => [RegistrationSheetRow::HEADERS],
                ], JSON_THROW_ON_ERROR));
            }
            if ($method === 'PUT' || ($method === 'POST' && str_contains($url, ':append'))) {
                $bodies[] = json_decode($options['body'] ?? '{}', true)['values'][0] ?? null;
            }

            return new MockResponse('{}');
        });
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://docs.google.com/spreadsheets/d/prod-sheet/edit'),
            new NullLogger(),
            accessToken: 'tok',
        );
        $client->exportApplication($this->row());

        self::assertSame(RegistrationSheetRow::RUSSIAN_HEADERS, $bodies[0]);
        self::assertSame('Ada', $bodies[1][0] ?? null);
    }

    public function testCreatesRegistrationsTabWhenMissing(): void
    {
        $urls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;
            if ($method === 'GET' && str_contains($url, '/values/')) {
                static $valuesCalls = 0;
                ++$valuesCalls;
                if ($valuesCalls === 1) {
                    return new MockResponse('{"error":{"message":"Unable to parse range"}}', ['http_code' => 400]);
                }

                return new MockResponse('{}');
            }
            if ($method === 'GET') {
                return new MockResponse('{"sheets":[{"properties":{"title":"Лист1"}}]}');
            }
            if ($method === 'POST' && str_contains($url, 'batchUpdate')) {
                return new MockResponse('{}');
            }

            return new MockResponse('{}');
        });
        $client = new GoogleSheetsClient(
            $httpClient,
            new GoogleSheetsRegistrationsReference('https://docs.google.com/spreadsheets/d/prod-sheet/edit'),
            new NullLogger(),
            accessToken: 'tok',
        );
        $client->exportApplication($this->row());

        self::assertTrue($this->urlsContain($urls, ':batchUpdate'));
        self::assertTrue($this->urlsContain($urls, '/values/'));
    }

    /**
     * @param list<string> $urls
     */
    private function urlsContain(array $urls, string $needle): bool
    {
        foreach ($urls as $url) {
            if (str_contains($url, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function row(): RegistrationSheetRow
    {
        return new RegistrationSheetRow(
            name: 'Ada',
            phone: '+7900',
            email: 'a@b.c',
            adultsCount: '1',
            childrenCount: '0',
            totalAmount: '2.00',
            payNowAmount: '1.00',
            participationOptionName: 'Option',
            transferIncluded: '0',
            paymentFactor: '0.5',
            notes: '',
            payment1Amount: '',
            payment1Date: '',
            payment1Id: '',
            payment2Amount: '',
            payment2Date: '',
            payment2Id: '',
            paidTotal: '0.00',
            remaining: '2.00',
            pricingPeriodName: 'Period',
            applicationUuid: 'uuid-1',
        );
    }
}
