<?php

namespace App\Tests\Integration\Service;

use App\Entity\HomeHero;
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

        $aboutHtml = $this->entityManager->getRepository(HomeHero::class)->findOneBy([])?->getAboutHtml() ?? '';
        self::assertStringContainsString('<h2', $aboutHtml);
        self::assertStringContainsString('text-brand-main', $aboutHtml);
        self::assertStringContainsString('В 100 км от Екатеринбурга', $aboutHtml);
        self::assertStringContainsString('<h4', $aboutHtml);
    }

    public function testFlushingHeroKeepsAboutHeadingMarkup(): void
    {
        /** @var SiteContentDefaults $defaults */
        $defaults = static::getContainer()->get(SiteContentDefaults::class);
        $defaults->ensureCoreContent();

        $hero = $this->entityManager->getRepository(HomeHero::class)->findOneBy([]);
        self::assertNotNull($hero);

        $hero->setAboutHtml(
            '<h2 class="text-brand-main mb-4" style="text-align: center;">В 100 км от Екатеринбурга и в 100 км от Челябинска</h2>',
        );
        $this->entityManager->flush();
        $this->entityManager->refresh($hero);

        $aboutHtml = $hero->getAboutHtml() ?? '';
        self::assertStringContainsString('<h2', $aboutHtml);
        self::assertStringContainsString('text-brand-main', $aboutHtml);
        self::assertStringContainsString('В 100 км от Екатеринбурга и в 100 км от Челябинска', $aboutHtml);
    }

    public function testEnsureCoreContentIsIdempotent(): void
    {
        /** @var SiteContentDefaults $defaults */
        $defaults = static::getContainer()->get(SiteContentDefaults::class);
        $defaults->ensureCoreContent();

        self::assertFalse($defaults->ensureCoreContent());
    }

    public function testEnsureCoreContentDoesNotRestoreDeletedLogoOnExistingSettings(): void
    {
        /** @var SiteContentDefaults $defaults */
        $defaults = static::getContainer()->get(SiteContentDefaults::class);
        $defaults->ensureCoreContent();

        $settings = $this->entityManager->getRepository(SiteSettings::class)->findOneBy([]);
        self::assertNotNull($settings);
        $settings->setLogoPath(null);
        $this->entityManager->flush();

        self::assertFalse($defaults->ensureCoreContent());
        $this->entityManager->refresh($settings);
        self::assertNull($settings->getLogoPath());
    }

    public function testEnsureCoreContentDoesNotRestoreDeletedHeroMedia(): void
    {
        /** @var SiteContentDefaults $defaults */
        $defaults = static::getContainer()->get(SiteContentDefaults::class);
        $defaults->ensureCoreContent();

        $hero = $this->entityManager->getRepository(HomeHero::class)->findOneBy([]);
        self::assertNotNull($hero);
        $hero->setImagePath(null);
        $hero->setPromoVideoLeft(null);
        $hero->setPromoVideoRight(null);
        $this->entityManager->flush();

        self::assertFalse($defaults->ensureCoreContent());
        $this->entityManager->refresh($hero);
        self::assertNull($hero->getImagePath());
        self::assertNull($hero->getPromoVideoLeft());
        self::assertNull($hero->getPromoVideoRight());
    }
}
