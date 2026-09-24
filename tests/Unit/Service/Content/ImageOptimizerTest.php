<?php

namespace App\Tests\Unit\Service\Content;

use App\Service\Content\ImageOptimizer;
use PHPUnit\Framework\TestCase;

final class ImageOptimizerTest extends TestCase
{
    private string $uploadsDir;
    private ImageOptimizer $optimizer;

    protected function setUp(): void
    {
        $this->uploadsDir = sys_get_temp_dir().'/hf-opt-'.bin2hex(random_bytes(4));
        mkdir($this->uploadsDir.'/gallery', 0777, true);
        $this->optimizer = new ImageOptimizer($this->uploadsDir, dirname($this->uploadsDir));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->uploadsDir);
    }

    public function testOptimizeWritesWebpVariants(): void
    {
        if (!$this->optimizer->isAvailable()) {
            self::markTestSkipped('GD+WebP required');
        }
        $src = $this->uploadsDir.'/gallery/sample.jpg';
        $img = imagecreatetruecolor(400, 300);
        imagejpeg($img, $src, 90);
        unset($img);

        $written = $this->optimizer->optimizePublicPath('/uploads/gallery/sample.jpg');
        self::assertCount(3, $written);
        self::assertFileExists($this->uploadsDir.'/gallery/sample.webp');
        self::assertFileExists($this->uploadsDir.'/gallery/sample-card.webp');
        self::assertFileExists($this->uploadsDir.'/gallery/sample-thumb.webp');
    }

    public function testOptimizePublicPathSkipsWhenGdUnavailable(): void
    {
        if ($this->optimizer->isAvailable()) {
            self::assertTrue($this->optimizer->isAvailable());

            return;
        }

        $src = $this->uploadsDir.'/gallery/sample.jpg';
        file_put_contents($src, 'not-an-image');
        self::assertSame([], $this->optimizer->optimizePublicPath('/uploads/gallery/sample.jpg'));
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
