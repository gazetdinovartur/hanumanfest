<?php

namespace App\Tests\Unit\Service\Content;

use App\Service\Content\ImageVariantResolver;
use App\Service\Content\UploadFileRemover;
use PHPUnit\Framework\TestCase;

final class UploadFileRemoverTest extends TestCase
{
    private string $uploadsDir;

    protected function setUp(): void
    {
        $this->uploadsDir = sys_get_temp_dir().'/hf-uploads-'.bin2hex(random_bytes(4));
        mkdir($this->uploadsDir.'/gallery', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->uploadsDir);
    }

    public function testDeletePublicPathRemovesFile(): void
    {
        $file = $this->uploadsDir.'/gallery/photo.jpg';
        file_put_contents($file, 'x');

        $resolver = new ImageVariantResolver($this->uploadsDir, dirname($this->uploadsDir));
        (new UploadFileRemover($this->uploadsDir, $resolver))->deletePublicPath('/uploads/gallery/photo.jpg');

        self::assertFileDoesNotExist($file);
    }

    public function testDeletePublicPathAlsoRemovesSidecars(): void
    {
        $file = $this->uploadsDir.'/gallery/photo.jpg';
        $card = $this->uploadsDir.'/gallery/photo-card.webp';
        file_put_contents($file, 'x');
        file_put_contents($card, 'y');

        $resolver = new ImageVariantResolver($this->uploadsDir, dirname($this->uploadsDir));
        (new UploadFileRemover($this->uploadsDir, $resolver))->deletePublicPath('/uploads/gallery/photo.jpg');

        self::assertFileDoesNotExist($file);
        self::assertFileDoesNotExist($card);
    }

    public function testDeletePublicPathKeepsLegacyWpMedia(): void
    {
        mkdir($this->uploadsDir.'/wp/2025/10', 0775, true);
        $file = $this->uploadsDir.'/wp/2025/10/logo-hanuman.png';
        file_put_contents($file, 'logo');

        $resolver = new ImageVariantResolver($this->uploadsDir, dirname($this->uploadsDir));
        (new UploadFileRemover($this->uploadsDir, $resolver))->deletePublicPath('/uploads/wp/2025/10/logo-hanuman.png');

        self::assertFileExists($file);
    }

    public function testDeletePublicPathIgnoresExternalUrls(): void
    {
        $file = $this->uploadsDir.'/gallery/photo.jpg';
        file_put_contents($file, 'x');

        $resolver = new ImageVariantResolver($this->uploadsDir, dirname($this->uploadsDir));
        (new UploadFileRemover($this->uploadsDir, $resolver))->deletePublicPath('https://example.com/uploads/gallery/photo.jpg');

        self::assertFileExists($file);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->removeTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
