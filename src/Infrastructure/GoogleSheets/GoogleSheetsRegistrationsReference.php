<?php

namespace App\Infrastructure\GoogleSheets;

/**
 * Одна настройка GOOGLE_SHEETS_WEBHOOK_URL (Apps Script) + таблица «Регистрации»
 * из legacy/google-apps-script/Code.by-columns.gs.
 */
final class GoogleSheetsRegistrationsReference
{
    /** @see legacy/google-apps-script/Code.by-columns.gs */
    private const DEFAULT_SPREADSHEET_ID = '1r2LoY04p4pCoknF7s14VkGBnTz-IxHaIkG8Un3W1bA0';

    private const DEFAULT_SHEET_NAME = 'Регистрации';

    public function __construct(
        private readonly string $configuredUrl = '',
    ) {
    }

    public function webhookUrl(): string
    {
        return $this->isAppsScriptWebhook($this->configuredUrl) ? trim($this->configuredUrl) : '';
    }

    public function spreadsheetViewUrl(): ?string
    {
        $spreadsheetId = $this->resolveSpreadsheetId();
        if ($spreadsheetId === '') {
            return null;
        }

        return sprintf('https://docs.google.com/spreadsheets/d/%s/edit', $spreadsheetId);
    }

    public function csvExportUrl(): ?string
    {
        $spreadsheetId = $this->resolveSpreadsheetId();
        if ($spreadsheetId === '') {
            return null;
        }

        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&sheet=%s',
            $spreadsheetId,
            rawurlencode(self::DEFAULT_SHEET_NAME),
        );
    }

    public function isConfigured(): bool
    {
        return trim($this->configuredUrl) !== '';
    }

    private function resolveSpreadsheetId(): string
    {
        if ($id = $this->extractSpreadsheetId($this->configuredUrl)) {
            return $id;
        }

        if ($this->isAppsScriptWebhook($this->configuredUrl)) {
            return self::DEFAULT_SPREADSHEET_ID;
        }

        return '';
    }

    private function isAppsScriptWebhook(string $url): bool
    {
        return str_contains($url, 'script.google.com') && str_contains($url, '/macros/s/');
    }

    private function extractSpreadsheetId(string $url): ?string
    {
        if (preg_match('~docs\.google\.com/spreadsheets/d/([a-zA-Z0-9-_]+)~', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
