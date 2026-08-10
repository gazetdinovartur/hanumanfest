<?php

namespace App\Tests\Unit\Infrastructure\GoogleSheets;

use App\Infrastructure\GoogleSheets\GoogleSheetsRegistrationsReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GoogleSheetsRegistrationsReferenceTest extends TestCase
{
    #[DataProvider('webhookProvider')]
    public function testWebhookUrl(string $configured, string $expected): void
    {
        $reference = new GoogleSheetsRegistrationsReference($configured);
        self::assertSame($expected, $reference->webhookUrl());
    }

    public static function webhookProvider(): iterable
    {
        yield 'apps script' => [
            'https://script.google.com/macros/s/abc123/exec',
            'https://script.google.com/macros/s/abc123/exec',
        ];
        yield 'spreadsheet url' => [
            'https://docs.google.com/spreadsheets/d/abc123/edit',
            '',
        ];
        yield 'empty' => ['', ''];
    }

    public function testSpreadsheetViewUrlFromAppsScriptWebhook(): void
    {
        $reference = new GoogleSheetsRegistrationsReference('https://script.google.com/macros/s/test/exec');
        self::assertSame(
            'https://docs.google.com/spreadsheets/d/1r2LoY04p4pCoknF7s14VkGBnTz-IxHaIkG8Un3W1bA0/edit',
            $reference->spreadsheetViewUrl(),
        );
    }

    public function testCsvExportUrlFromAppsScriptWebhook(): void
    {
        $reference = new GoogleSheetsRegistrationsReference('https://script.google.com/macros/s/test/exec');
        self::assertSame(
            'https://docs.google.com/spreadsheets/d/1r2LoY04p4pCoknF7s14VkGBnTz-IxHaIkG8Un3W1bA0/gviz/tq?tqx=out:csv&sheet=%D0%A0%D0%B5%D0%B3%D0%B8%D1%81%D1%82%D1%80%D0%B0%D1%86%D0%B8%D0%B8',
            $reference->csvExportUrl(),
        );
    }
}
