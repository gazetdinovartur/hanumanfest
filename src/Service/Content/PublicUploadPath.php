<?php

namespace App\Service\Content;

final class PublicUploadPath
{
    public static function storagePath(string $subdir, string $basename): string
    {
        $subdir = trim($subdir, '/');
        $basename = ltrim(str_replace('\\', '/', $basename), '/');

        return '/uploads/'.$subdir.'/'.$basename;
    }

    public static function downloadDir(string $subdir): string
    {
        return 'uploads/'.trim($subdir, '/').'/';
    }

    /** @return array{basename: string, downloadDir: string}|null */
    public static function parseStored(?string $stored): ?array
    {
        if (null === $stored || '' === $stored) {
            return null;
        }

        if (filter_var($stored, FILTER_VALIDATE_URL)) {
            return null;
        }

        $path = str_replace('\\', '/', $stored);
        $path = ltrim($path, '/');

        if (!str_starts_with($path, 'uploads/')) {
            return null;
        }

        $relative = substr($path, strlen('uploads/'));
        if ('' === $relative || !str_contains($relative, '/')) {
            return [
                'basename' => $relative,
                'downloadDir' => 'uploads/',
            ];
        }

        $basename = basename($relative);
        if ('' === $basename) {
            return null;
        }

        $dir = substr($relative, 0, -\strlen($basename));

        return [
            'basename' => $basename,
            'downloadDir' => 'uploads/'.$dir,
        ];
    }

    public static function webPath(?string $stored): ?string
    {
        if (null === $stored || '' === $stored) {
            return null;
        }

        if (filter_var($stored, FILTER_VALIDATE_URL)) {
            return $stored;
        }

        $path = str_replace('\\', '/', $stored);
        if (str_starts_with($path, '/uploads/') || str_starts_with($path, 'uploads/')) {
            return str_starts_with($path, '/') ? $path : '/'.$path;
        }

        if (!str_contains($path, '/')) {
            return null;
        }

        return '/uploads/'.$path;
    }

    public static function relativeWithinUploads(?string $stored): ?string
    {
        $parsed = self::parseStored($stored);
        if (null === $parsed) {
            return null;
        }

        $dir = substr($parsed['downloadDir'], strlen('uploads/'));

        return $dir.$parsed['basename'];
    }
}
