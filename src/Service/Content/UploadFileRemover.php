<?php

namespace App\Service\Content;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Removes public upload files referenced by CMS entities (/uploads/… paths).
 */
final class UploadFileRemover
{
    public function __construct(
        #[Autowire('%app.uploads_directory%')]
        private readonly string $uploadsDirectory,
        private readonly ImageVariantResolver $variantResolver,
    ) {
    }

    public function deletePublicPath(?string $storedPath): void
    {
        if (null === $storedPath || '' === $storedPath) {
            return;
        }

        if (filter_var($storedPath, FILTER_VALIDATE_URL)) {
            return;
        }

        $path = str_replace('\\', '/', $storedPath);
        $path = ltrim($path, '/');
        if (!str_starts_with($path, 'uploads/')) {
            return;
        }

        $relative = substr($path, strlen('uploads/'));
        if ('' === $relative) {
            return;
        }

        // Импортированные WP-медиа могут шариться (логотип, hero, видео) — не удаляем.
        if (str_starts_with($relative, 'wp/') || str_starts_with($relative, 'wp\\')) {
            return;
        }

        $absolute = rtrim($this->uploadsDirectory, '/').'/'.$relative;
        if (is_file($absolute)) {
            @unlink($absolute);
        }

        foreach ($this->variantResolver->sidecarAbsolutePaths('/'.$path) as $sidecar) {
            if ($sidecar !== $absolute && is_file($sidecar)) {
                @unlink($sidecar);
            }
        }
    }
}
