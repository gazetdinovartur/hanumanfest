<?php

namespace App\Tests\Unit\Service\Content;

use App\Service\Content\ImageOptimizer;
use App\Service\Content\ImageVariantResolver;
use PHPUnit\Framework\TestCase;

final class ImageVariantResolverTest extends TestCase
{
    private string $uploadsDir;
    private ImageVariantResolver $resolver;

    protected function setUp(): void
    {
        $this->uploadsDir = sys_get_temp_dir().'/hf-uploads-'.bin2hex(random_bytes(4));
        mkdir($this->uploadsDir.'/gallery', 0777, true);
        $this->resolver = new ImageVariantResolver($this->uploadsDir, dirname($this->uploadsDir));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->uploadsDir);
    }

    public function testFallsBackToOriginalWhenVariantMissing(): void
    {
        file_put_contents($this->uploadsDir.'/gallery/photo.jpg', 'x');
        $resolved = $this->resolver->resolve('/uploads/gallery/photo.jpg', ImageOptimizer::VARIANT_CARD);
        self::assertSame('/uploads/gallery/photo.jpg', $resolved);
    }

    public function testPrefersExistingCardVariant(): void
    {
        file_put_contents($this->uploadsDir.'/gallery/photo.jpg', 'x');
        file_put_contents($this->uploadsDir.'/gallery/photo-card.webp', 'y');
        $resolved = $this->resolver->resolve('/uploads/gallery/photo.jpg', ImageOptimizer::VARIANT_CARD);
        self::assertSame('/uploads/gallery/photo-card.webp', $resolved);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
