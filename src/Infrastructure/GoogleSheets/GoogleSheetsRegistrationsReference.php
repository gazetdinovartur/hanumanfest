<?php

namespace App\Infrastructure\GoogleSheets;

/**
 * Ссылка на таблицу регистраций: REGISTRATION_SHEET_URL (docs.google.com/.../edit).
 * Экспорт строк — Google Sheets API + service account.
 */
final class GoogleSheetsRegistrationsReference
{
    /** Лист регистраций по умолчанию. */
    private const DEFAULT_SPREADSHEET_ID = '1H5bzA14-b7vjZBjo6lz7ZyIlGcoZc26fWVs0aL31uV0';

    private const DEFAULT_SHEET_NAME = 'Регистрации';

    public function __construct(
        private readonly string $configuredUrl = '',
        private readonly ?string $fallbackSpreadsheetId = self::DEFAULT_SPREADSHEET_ID,
    ) {
    }

    public function spreadsheetId(): string
    {
        return $this->resolveSpreadsheetId();
    }

    public function sheetTitle(): string
    {
        return self::DEFAULT_SHEET_NAME;
    }

    public function webhookUrl(): string
    {
        return $this->isAppsScriptWebhook($this->configuredUrl) ? trim($this->configuredUrl) : '';
    }

    public function spreadsheetViewUrl(): string
    {
        $spreadsheetId = $this->resolveSpreadsheetId();
        if ($spreadsheetId === '') {
            return '';
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
        if ($id = GoogleSpreadsheetUrl::idFrom($this->configuredUrl)) {
            return $id;
        }

        $fallback = trim((string) $this->fallbackSpreadsheetId);
        if ($fallback !== '' && ($this->isAppsScriptWebhook($this->configuredUrl) || trim($this->configuredUrl) === '')) {
            return $fallback;
        }

        return '';
    }

    private function isAppsScriptWebhook(string $url): bool
    {
        return str_contains($url, 'script.google.com') && str_contains($url, '/macros/s/');
    }
}
