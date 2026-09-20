<?php

namespace App\Tests\Integration\Service;

use App\Entity\HomeHighlight;
use App\Entity\SiteSettings;
use App\Service\Content\SiteContentDefaults;
use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class SiteContentDefaultsTest extends DatabaseTestCase
{
    public function testEnsureCoreContentFillsEmptyDatabase(): void
    {
        /** @var SiteContentDefaults $defaults */
        $defaults = static::getContainer()->get(SiteContentDefaults::class);

        self::assertTrue($defaults->isCoreEmpty());
        self::assertTrue($defaults->ensureCoreContent());
        self::assertFalse($defaults->isCoreEmpty());
        self::assertGreaterThan(0, $this->entityManager->getRepository(HomeHighlight::class)->count([]));
        self::assertNotNull($this->entityManager->getRepository(SiteSettings::class)->findOneBy([])?->getDiscountsHtml());
    }

    public function testEnsureCoreContentIsIdempotent(): void
    {
        /** @var SiteContentDefaults $defaults */
        $defaults = static::getContainer()->get(SiteContentDefaults::class);
        $defaults->ensureCoreContent();

        self::assertFalse($defaults->ensureCoreContent());
    }

    public function testEnsureCoreContentRestoresMissingLogoPath(): void
    {
        /** @var SiteContentDefaults $defaults */
        $defaults = static::getContainer()->get(SiteContentDefaults::class);
        $defaults->ensureCoreContent();

        $settings = $this->entityManager->getRepository(SiteSettings::class)->findOneBy([]);
        self::assertNotNull($settings);
        $settings->setLogoPath(null);
        $this->entityManager->flush();

        self::assertTrue($defaults->ensureCoreContent());
        $this->entityManager->refresh($settings);
        self::assertSame('/uploads/wp/2025/10/logo-hanuman.png', $settings->getLogoPath());
        self::assertFalse($defaults->ensureCoreContent());
    }
}
