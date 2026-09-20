<?php

namespace App\Infrastructure\GoogleSheets;

use App\Infrastructure\GoogleSheets\Dto\RegistrationSheetRow;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleSheetsClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SHEETS_API = 'https://sheets.googleapis.com/v4/spreadsheets';
    private const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    private ?string $cachedToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GoogleSheetsRegistrationsReference $registrations,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?GoogleSheetsRegistrationsReference $testRegistrations = null,
        private readonly string $credentials = '',
        private readonly ?string $accessToken = null,
        private readonly string $projectDir = '',
    ) {
    }

    public function exportApplication(RegistrationSheetRow $row, bool $isTest = false): void
    {
        $this->write($row, $isTest, RegistrationSheetRow::APPLICATION_FIELDS, skipIfExists: true);
    }

    public function exportPayment(RegistrationSheetRow $row, bool $isTest = false): void
    {
        $this->write($row, $isTest, RegistrationSheetRow::PAYMENT_FIELDS, skipIfExists: false);
    }

    /**
     * @param list<string> $fields
     */
    private function write(RegistrationSheetRow $row, bool $isTest, array $fields, bool $skipIfExists): void
    {
        $target = $this->target($isTest);
        $spreadsheetId = $target?->spreadsheetId() ?? '';
        $token = $this->resolveAccessToken();
        if ($target === null || $spreadsheetId === '' || $token === null) {
            $this->logger?->warning('Google Sheets export skipped (no spreadsheet or credentials)');

            return;
        }

        try {
            $this->upsert($spreadsheetId, $target->sheetTitle(), $token, $row, $fields, $skipIfExists);
        } catch (\Throwable) {
            try {
                $this->ensureSheet($spreadsheetId, $target->sheetTitle(), $token);
                $this->upsert($spreadsheetId, $target->sheetTitle(), $token, $row, $fields, $skipIfExists);
            } catch (\Throwable $e) {
                $this->logger?->error('Google Sheets export failed', [
                    'isTest' => $isTest,
                    'applicationUuid' => $row->applicationUuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param list<string> $fields
     */
    private function upsert(
        string $spreadsheetId,
        string $sheetTitle,
        string $token,
        RegistrationSheetRow $row,
        array $fields,
        bool $skipIfExists,
    ): void {
        $headerRows = $this->getValues($spreadsheetId, $sheetTitle, $token, $this->a1($sheetTitle, '1:2'));
        $headers = $this->headersFrom($headerRows);
        if ($headers === []) {
            $this->putRow($spreadsheetId, $sheetTitle, $token, 1, RegistrationSheetRow::HEADERS);
            $this->putRow($spreadsheetId, $sheetTitle, $token, 2, RegistrationSheetRow::RUSSIAN_HEADERS);
            $this->appendRow($spreadsheetId, $sheetTitle, $token, $row->valuesFor(RegistrationSheetRow::HEADERS));

            return;
        }
        if (\count($headerRows) === 1) {
            $this->putRow($spreadsheetId, $sheetTitle, $token, 2, RegistrationSheetRow::russianLabelsFor($headers));
        }

        $existingRow = $this->findUuidRow($spreadsheetId, $sheetTitle, $token, $headers, $row->applicationUuid);
        if ($existingRow !== null && $skipIfExists) {
            return;
        }

        if ($existingRow === null) {
            $this->appendRow($spreadsheetId, $sheetTitle, $token, $row->valuesFor($headers));

            return;
        }

        $last = $this->columnLetter(max(1, \count($headers)));
        $rowValues = $this->getValues($spreadsheetId, $sheetTitle, $token, $this->a1($sheetTitle, 'A'.$existingRow.':'.$last.$existingRow));
        $merged = $rowValues[0] ?? [];
        $assoc = $row->toAssoc();
        foreach ($fields as $field) {
            if ($field === 'applicationUuid') {
                continue;
            }
            $col = $this->headerIndex($headers, $field);
            if ($col === null) {
                continue;
            }
            while (\count($merged) <= $col) {
                $merged[] = '';
            }
            $merged[$col] = $assoc[$field] ?? '';
        }

        $this->putRow($spreadsheetId, $sheetTitle, $token, $existingRow, $merged);
    }

    private function ensureSheet(string $spreadsheetId, string $sheetTitle, string $token): void
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf('%s/%s?fields=sheets.properties.title', self::SHEETS_API, rawurlencode($spreadsheetId)),
            ['headers' => ['Authorization' => 'Bearer '.$token], 'timeout' => 8],
        );
        /** @var array{sheets?: list<array{properties?: array{title?: string}}>} $data */
        $data = $response->toArray(false);
        foreach ($data['sheets'] ?? [] as $sheet) {
            if (($sheet['properties']['title'] ?? '') === $sheetTitle) {
                return;
            }
        }

        $this->httpClient->request(
            'POST',
            sprintf('%s/%s:batchUpdate', self::SHEETS_API, rawurlencode($spreadsheetId)),
            [
                'timeout' => 8,
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'requests' => [
                        ['addSheet' => ['properties' => ['title' => $sheetTitle]]],
                    ],
                ],
            ],
        );
    }

    /**
     * @return list<list<string>>
     */
    private function getValues(string $spreadsheetId, string $sheetTitle, string $token, ?string $range = null): array
    {
        $response = $this->httpClient->request('GET', $this->valuesUrl($spreadsheetId, $range ?? $this->quotedSheet($sheetTitle)), [
            'headers' => ['Authorization' => 'Bearer '.$token],
            'timeout' => 12,
        ]);
        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Google Sheets values request failed');
        }
        /** @var array{values?: list<list<mixed>>} $data */
        $data = $response->toArray(false);
        $rows = [];
        foreach ($data['values'] ?? [] as $row) {
            $rows[] = array_map(static fn (mixed $cell): string => \is_scalar($cell) ? (string) $cell : '', $row);
        }

        return $rows;
    }

    /**
     * @param list<string> $headers
     */
    private function findUuidRow(
        string $spreadsheetId,
        string $sheetTitle,
        string $token,
        array $headers,
        string $uuid,
    ): ?int {
        if ($uuid === '') {
            return null;
        }
        $col = $this->headerIndex($headers, 'applicationUuid');
        if ($col === null) {
            return null;
        }
        $letter = $this->columnLetter($col + 1);
        $column = $this->getValues($spreadsheetId, $sheetTitle, $token, $this->a1($sheetTitle, $letter.':'.$letter));
        foreach ($column as $i => $row) {
            if (($row[0] ?? '') === $uuid) {
                return $i + 1;
            }
        }

        return null;
    }

    private function a1(string $sheetTitle, string $range): string
    {
        return $this->quotedSheet($sheetTitle).'!'.$range;
    }

    /**
     * @param list<string> $row
     */
    private function putRow(string $spreadsheetId, string $sheetTitle, string $token, int $rowNumber, array $row): void
    {
        $last = $this->columnLetter(max(1, \count($row)));
        $range = sprintf("%s!A%d:%s%d", $this->quotedSheet($sheetTitle), $rowNumber, $last, $rowNumber);
        $this->httpClient->request('PUT', $this->valuesUrl($spreadsheetId, $range).'?valueInputOption=RAW', [
            'timeout' => 8,
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ],
            'json' => ['values' => [$row]],
        ]);
    }

    /**
     * @param list<string> $row
     */
    private function appendRow(string $spreadsheetId, string $sheetTitle, string $token, array $row): void
    {
        $range = sprintf('%s!A1', $this->quotedSheet($sheetTitle));
        $this->httpClient->request(
            'POST',
            $this->valuesUrl($spreadsheetId, $range).':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
            [
                'timeout' => 8,
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['values' => [$row]],
            ],
        );
    }

    /**
     * @param list<list<string>> $values
     * @return list<string>
     */
    private function headersFrom(array $values): array
    {
        if ($values === []) {
            return [];
        }
        $first = $values[0];
        $normalized = array_map($this->normalizeHeader(...), $first);
        if (!\in_array('name', $normalized, true) && !\in_array('applicationuuid', $normalized, true)) {
            return [];
        }

        return $first;
    }

    /**
     * @param list<string> $headers
     */
    private function headerIndex(array $headers, string $name): ?int
    {
        $want = $this->normalizeHeader($name);
        foreach ($headers as $i => $header) {
            if ($this->normalizeHeader($header) === $want) {
                return $i;
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(preg_replace('/\s+/', '', trim($header)) ?? '');
    }

    private function quotedSheet(string $sheetTitle): string
    {
        return "'".str_replace("'", "''", $sheetTitle)."'";
    }

    private function valuesUrl(string $spreadsheetId, string $range): string
    {
        return sprintf('%s/%s/values/%s', self::SHEETS_API, rawurlencode($spreadsheetId), rawurlencode($range));
    }

    private function columnLetter(int $count): string
    {
        $n = $count;
        $letter = '';
        while ($n > 0) {
            $n--;
            $letter = \chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26);
        }

        return $letter !== '' ? $letter : 'A';
    }

    private function target(bool $isTest): ?GoogleSheetsRegistrationsReference
    {
        if ($isTest) {
            $test = $this->testRegistrations;
            if ($test === null || $test->spreadsheetId() === '') {
                return null;
            }

            return $test;
        }

        return $this->registrations;
    }

    private function resolveAccessToken(): ?string
    {
        if ($this->accessToken !== null && $this->accessToken !== '') {
            return $this->accessToken;
        }
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $credentials = $this->parseCredentials();
        if ($credentials === null) {
            return null;
        }

        $jwt = $this->signedJwt($credentials);
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'timeout' => 8,
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);
        /** @var array{access_token?: string} $data */
        $data = $response->toArray(false);
        $token = $data['access_token'] ?? '';
        if ($token === '') {
            return null;
        }

        return $this->cachedToken = $token;
    }

    /**
     * @return array{client_email: string, private_key: string}|null
     */
    private function parseCredentials(): ?array
    {
        $raw = trim($this->credentials);
        if ($raw === '') {
            return null;
        }
        if (!str_starts_with($raw, '{')) {
            $path = $raw;
            if (!str_starts_with($path, '/') && $this->projectDir !== '') {
                $path = rtrim($this->projectDir, '/').'/'.preg_replace('#^\./#', '', $path);
            }
            if (!is_file($path)) {
                return null;
            }
            $raw = (string) file_get_contents($path);
        }
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        $email = trim((string) ($data['client_email'] ?? ''));
        $key = (string) ($data['private_key'] ?? '');
        if ($email === '' || $key === '') {
            return null;
        }

        return ['client_email' => $email, 'private_key' => $key];
    }

    /**
     * @param array{client_email: string, private_key: string} $credentials
     */
    private function signedJwt(array $credentials): string
    {
        $now = time();
        $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->b64url(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;
        $ok = openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new \RuntimeException('Failed to sign Google service account JWT');
        }

        return $unsigned.'.'.$this->b64url($signature);
    }

    private function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
