<?php

namespace App\Service\Content;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generates high-quality WebP sidecars (thumb / card / display) next to an upload.
 */
final class ImageOptimizer
{
    public const VARIANT_THUMB = 'thumb';
    public const VARIANT_CARD = 'card';
    public const VARIANT_DISPLAY = 'display';

    private const QUALITY = 85;

    /** @var array<string, array{max: int, square: bool}> */
    private const VARIANTS = [
        self::VARIANT_THUMB => ['max' => 180, 'square' => true],
        self::VARIANT_CARD => ['max' => 800, 'square' => false],
        self::VARIANT_DISPLAY => ['max' => 1600, 'square' => false],
    ];

    public function __construct(
        #[Autowire('%app.uploads_directory%')]
        private readonly string $uploadsDirectory,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function assertAvailable(): void
    {
        if (!\extension_loaded('gd')) {
            throw new \RuntimeException('Расширение PHP GD не установлено. На Sweb включите GD с поддержкой WebP.');
        }
        if (!\function_exists('imagewebp')) {
            throw new \RuntimeException('GD без WebP. На Sweb нужна сборка PHP с WebP Support.');
        }
    }

    /**
     * @return list<string> Absolute paths of written variant files
     */
    public function optimizePublicPath(string $storedPath): array
    {
        $this->assertAvailable();
        $absolute = $this->absoluteFromPublicPath($storedPath);
        if (!is_file($absolute)) {
            throw new \InvalidArgumentException('Файл не найден: '.$storedPath);
        }

        return $this->optimizeAbsolute($absolute);
    }

    /**
     * Copy/optimize a WP (or any) source into $targetSubdir and return new public path.
     * Writes display WebP as the canonical file plus thumb/card sidecars.
     */
    public function relocateFromWp(string $storedPath, string $targetSubdir): string
    {
        $this->assertAvailable();
        $source = $this->absoluteFromPublicPath($storedPath);
        if (!is_file($source)) {
            throw new \InvalidArgumentException('Источник не найден: '.$storedPath);
        }

        $subdir = trim(str_replace('\\', '/', $targetSubdir), '/');
        $dir = rtrim($this->uploadsDirectory, '/').'/'.$subdir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create upload directory: '.$dir);
        }

        $base = bin2hex(random_bytes(8));
        $displayPath = $dir.'/'.$base.'.webp';
        $this->writeVariantsFromSource($source, $dir.'/'.$base);
        if (!is_file($displayPath)) {
            throw new \RuntimeException('Не удалось создать WebP: '.$displayPath);
        }

        return '/uploads/'.$subdir.'/'.$base.'.webp';
    }

    /**
     * @return list<string>
     */
    public function optimizeAbsolute(string $absolutePath): array
    {
        $this->assertAvailable();
        $info = pathinfo($absolutePath);
        $dir = $info['dirname'] ?? '';
        $filename = $info['filename'] ?? '';
        if ($dir === '' || $filename === '') {
            throw new \InvalidArgumentException('Некорректный путь: '.$absolutePath);
        }

        // Avoid double-suffix when re-optimizing an existing display webp named foo.webp
        $baseName = preg_replace('/-(?:thumb|card)$/', '', $filename) ?? $filename;

        return $this->writeVariantsFromSource($absolutePath, $dir.'/'.$baseName);
    }

    /**
     * @return list<string>
     */
    private function writeVariantsFromSource(string $sourceAbsolute, string $baseWithoutExt): array
    {
        $image = $this->loadImage($sourceAbsolute);
        $srcW = imagesx($image);
        $srcH = imagesy($image);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($image);
            throw new \RuntimeException('Пустое изображение: '.$sourceAbsolute);
        }

        $written = [];
        foreach (self::VARIANTS as $kind => $cfg) {
            $variant = $this->resize($image, $srcW, $srcH, $cfg['max'], $cfg['square']);
            $suffix = match ($kind) {
                self::VARIANT_THUMB => '-thumb',
                self::VARIANT_CARD => '-card',
                default => '',
            };
            $target = $baseWithoutExt.$suffix.'.webp';
            if (!imagewebp($variant, $target, self::QUALITY)) {
                unset($variant, $image);
                throw new \RuntimeException('Не удалось записать WebP: '.$target);
            }
            unset($variant);
            $written[] = $target;
        }

        unset($image);

        return $written;
    }

    private function loadImage(string $absolutePath): \GdImage
    {
        $data = @file_get_contents($absolutePath);
        if (false === $data || '' === $data) {
            throw new \RuntimeException('Не удалось прочитать файл: '.$absolutePath);
        }
        $image = @imagecreatefromstring($data);
        if (!$image instanceof \GdImage) {
            throw new \RuntimeException('Формат изображения не поддерживается: '.$absolutePath);
        }
        if (\function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }
        if (\function_exists('imagealphablending') && \function_exists('imagesavealpha')) {
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        return $image;
    }

    private function resize(\GdImage $source, int $srcW, int $srcH, int $max, bool $square): \GdImage
    {
        if ($square) {
            $side = min($srcW, $srcH, $max);
            $dst = imagecreatetruecolor($side, $side);
            $this->fillTransparent($dst);
            $crop = min($srcW, $srcH);
            $srcX = (int) (($srcW - $crop) / 2);
            $srcY = (int) (($srcH - $crop) / 2);
            imagecopyresampled($dst, $source, 0, 0, $srcX, $srcY, $side, $side, $crop, $crop);

            return $dst;
        }

        $long = max($srcW, $srcH);
        if ($long <= $max) {
            $dstW = $srcW;
            $dstH = $srcH;
        } else {
            $scale = $max / $long;
            $dstW = max(1, (int) round($srcW * $scale));
            $dstH = max(1, (int) round($srcH * $scale));
        }

        $dst = imagecreatetruecolor($dstW, $dstH);
        $this->fillTransparent($dst);
        imagecopyresampled($dst, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        return $dst;
    }

    private function fillTransparent(\GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        imagealphablending($image, true);
    }

    public function absoluteFromPublicPath(string $storedPath): string
    {
        if (filter_var($storedPath, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Внешние URL не оптимизируются.');
        }
        $path = str_replace('\\', '/', $storedPath);
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'uploads/')) {
            return rtrim($this->uploadsDirectory, '/').'/'.substr($path, strlen('uploads/'));
        }

        // Absolute under project (rare)
        if (str_starts_with($path, $this->projectDir)) {
            return $path;
        }

        return rtrim($this->projectDir, '/').'/public/'.$path;
    }
}
