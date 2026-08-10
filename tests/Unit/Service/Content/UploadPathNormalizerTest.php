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
        self::assertSame('photo.jpg', $item->getImagePath());
        self::assertFalse($this->normalizer->usesUploadsRoot($item, 'imagePath'));
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
        self::assertTrue($this->normalizer->usesUploadsRoot($item, 'imagePath'));
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
}
