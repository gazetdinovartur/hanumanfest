<?php

namespace App\Infrastructure\GoogleSheets;

final class GoogleSpreadsheetUrl
{
    public static function idFrom(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (preg_match('~docs\.google\.com/spreadsheets/d/([a-zA-Z0-9-_]+)~', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function editUrlFrom(?string $url): ?string
    {
        $id = self::idFrom($url);
        if ($id === null) {
            return null;
        }

        return sprintf('https://docs.google.com/spreadsheets/d/%s/edit', $id);
    }
}
