<?php

namespace App\Tests\Unit\Service\Content;

use App\Service\Content\PublicUploadPath;
use App\Service\Content\UploadPathNormalizer;
use PHPUnit\Framework\TestCase;

final class PublicUploadPathTest extends TestCase
{
    public function testParseStoredLegacyWpPath(): void
    {
        $parsed = PublicUploadPath::parseStored('/uploads/wp/2025/10/logo.png');
        self::assertNotNull($parsed);
        self::assertSame('logo.png', $parsed['basename']);
        self::assertSame('uploads/wp/2025/10/', $parsed['downloadDir']);
    }

    public function testRelativeWithinUploads(): void
    {
        self::assertSame(
            'wp/2025/10/logo.png',
            PublicUploadPath::relativeWithinUploads('/uploads/wp/2025/10/logo.png'),
        );
    }

    public function testWebPathFromStored(): void
    {
        self::assertSame('/uploads/hero/bg.jpg', PublicUploadPath::webPath('/uploads/hero/bg.jpg'));
    }

    public function testWebPathFromFormRelative(): void
    {
        self::assertSame('/uploads/wp/2025/10/logo.png', PublicUploadPath::webPath('wp/2025/10/logo.png'));
    }
}

final class UploadPathNormalizerTest extends TestCase
{
    private UploadPathNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UploadPathNormalizer();
    }

    public function testStripForFormOnlyOwnSubdir(): void
    {
        $item = new class {
            private string $imagePath = '/uploads/gallery/photo.jpg';

            public function getImagePath(): string
            {
                return $this->imagePath;
            }

            public function setImagePath(string $v): void
            {
                $this->imagePath = $v;
            }
        };

        $this->normalizer->stripForForm($item, ['imagePath' => 'gallery']);
        self::assertSame('gallery/photo.jpg', $item->getImagePath());
    }

    public function testStripForFormHandlesLegacyWpPath(): void
    {
        $item = new class {
            private string $imagePath = '/uploads/wp/2025/10/logo.png';

            public function getImagePath(): string
            {
                return $this->imagePath;
            }

            public function setImagePath(string $v): void
            {
                $this->imagePath = $v;
            }
        };

        $this->normalizer->stripForForm($item, ['imagePath' => 'site']);
        self::assertSame('wp/2025/10/logo.png', $item->getImagePath());
    }

    public function testExpandForStorageAddsPrefixForBasename(): void
    {
        $item = new class {
            private string $imagePath = 'new.jpg';

            public function getImagePath(): string
            {
                return $this->imagePath;
            }

            public function setImagePath(string $v): void
            {
                $this->imagePath = $v;
            }
        };

        $this->normalizer->expandForStorage($item, ['imagePath' => 'gallery']);
        self::assertSame('/uploads/gallery/new.jpg', $item->getImagePath());
    }

    public function testExpandForStorageKeepsLegacyRelativePath(): void
    {
        $item = new class {
            private string $imagePath = 'wp/2025/10/logo.png';

            public function getImagePath(): string
            {
                return $this->imagePath;
            }

            public function setImagePath(string $v): void
            {
                $this->imagePath = $v;
            }
        };

        $this->normalizer->expandForStorage($item, ['imagePath' => 'site']);
        self::assertSame('/uploads/wp/2025/10/logo.png', $item->getImagePath());
    }

    public function testExpandForStorageClearsEmptyStringToNull(): void
    {
        $item = new class {
            private ?string $imagePath = '';

            public function getImagePath(): ?string
            {
                return $this->imagePath;
            }

            public function setImagePath(?string $v): void
            {
                $this->imagePath = $v;
            }
        };

        $this->normalizer->expandForStorage($item, ['imagePath' => 'hero']);
        self::assertNull($item->getImagePath());
    }

    public function testExpandForStorageKeepsRelativeHeroPath(): void
    {
        $item = new class {
            private string $imagePath = 'hero/new.jpg';

            public function getImagePath(): string
            {
                return $this->imagePath;
            }

            public function setImagePath(string $v): void
            {
                $this->imagePath = $v;
            }
        };

        $this->normalizer->expandForStorage($item, ['imagePath' => 'hero']);
        self::assertSame('/uploads/hero/new.jpg', $item->getImagePath());
    }
}

final class PublicUploadDeleteTest extends TestCase
{
    public function testDeleteSkipsSharedWpOriginals(): void
    {
        $dir = sys_get_temp_dir().'/hf-wp-'.bin2hex(random_bytes(3)).'/uploads/wp/2025/10';
        mkdir($dir, 0775, true);
        $path = $dir.'/logo.png';
        file_put_contents($path, 'x');

        PublicUploadPath::deleteLocalFileUnlessSharedWp(new \SplFileInfo($path));
        self::assertFileExists($path);

        $this->removeTree(dirname($dir, 3));
    }

    public function testDeleteRemovesCmsUploads(): void
    {
        $dir = sys_get_temp_dir().'/hf-hero-'.bin2hex(random_bytes(3)).'/uploads/hero';
        mkdir($dir, 0775, true);
        $path = $dir.'/bg.png';
        file_put_contents($path, 'x');

        PublicUploadPath::deleteLocalFileUnlessSharedWp(new \SplFileInfo($path));
        self::assertFileDoesNotExist($path);

        $this->removeTree(dirname($dir, 2));
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
