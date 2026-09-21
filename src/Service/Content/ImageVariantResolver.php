<?php

namespace App\Service\Content;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves WebP sidecar variants for a stored public upload path.
 */
final class ImageVariantResolver
{
    public function __construct(
        #[Autowire('%app.uploads_directory%')]
        private readonly string $uploadsDirectory,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function resolve(?string $storedPath, string $variant = ImageOptimizer::VARIANT_DISPLAY): ?string
    {
        if (null === $storedPath || '' === $storedPath) {
            return $storedPath;
        }

        if (filter_var($storedPath, FILTER_VALIDATE_URL)) {
            return $storedPath;
        }

        $public = $this->normalizePublicPath($storedPath);
        $candidate = $this->variantPublicPath($public, $variant);
        if (null !== $candidate && $this->publicFileExists($candidate)) {
            return $candidate;
        }

        // Display may be the canonical .webp itself
        if ($variant === ImageOptimizer::VARIANT_DISPLAY && $this->publicFileExists($public)) {
            return $public;
        }

        return $public;
    }

    public function variantPublicPath(string $publicPath, string $variant): ?string
    {
        $path = str_replace('\\', '/', $publicPath);
        $dir = \dirname($path);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        if ($filename === '' || $filename === '.') {
            return null;
        }
        $base = preg_replace('/-(?:thumb|card)$/', '', $filename) ?? $filename;
        $suffix = match ($variant) {
            ImageOptimizer::VARIANT_THUMB => '-thumb',
            ImageOptimizer::VARIANT_CARD => '-card',
            ImageOptimizer::VARIANT_DISPLAY => '',
            default => null,
        };
        if (null === $suffix) {
            return null;
        }

        return ($dir === '/' ? '' : $dir).'/'.$base.$suffix.'.webp';
    }

    /**
     * @return list<string> Absolute sidecar paths that may exist next to the stored file
     */
    public function sidecarAbsolutePaths(string $storedPath): array
    {
        $public = $this->normalizePublicPath($storedPath);
        $paths = [];
        foreach ([ImageOptimizer::VARIANT_THUMB, ImageOptimizer::VARIANT_CARD, ImageOptimizer::VARIANT_DISPLAY] as $variant) {
            $candidate = $this->variantPublicPath($public, $variant);
            if (null !== $candidate) {
                $paths[] = $this->absoluteFromPublic($candidate);
            }
        }

        return array_values(array_unique($paths));
    }

    private function normalizePublicPath(string $storedPath): string
    {
        $path = str_replace('\\', '/', $storedPath);
        if (!str_starts_with($path, '/')) {
            $path = '/'.$path;
        }
        if (!str_starts_with($path, '/uploads/')) {
            // EasyAdmin may store relative without leading slash already handled
            if (str_starts_with(ltrim($path, '/'), 'uploads/')) {
                $path = '/'.ltrim($path, '/');
            }
        }

        return $path;
    }

    private function publicFileExists(string $publicPath): bool
    {
        return is_file($this->absoluteFromPublic($publicPath));
    }

    private function absoluteFromPublic(string $publicPath): string
    {
        $path = ltrim(str_replace('\\', '/', $publicPath), '/');
        if (str_starts_with($path, 'uploads/')) {
            return rtrim($this->uploadsDirectory, '/').'/'.substr($path, strlen('uploads/'));
        }

        return rtrim($this->projectDir, '/').'/public/'.$path;
    }
}
