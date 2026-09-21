<?php

namespace App\Service\Content;

use App\Entity\HomeHero;
use App\Entity\HomeHighlight;
use App\Entity\SiteSettings;
use App\Enum\HomeHighlightColumn;
use App\Enum\HomeHighlightStyle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Базовый контент главной (hero, настройки, плитки).
 * Единственный источник копирайта: data/site-content/default.json.
 */
final class SiteContentDefaults
{
    public const DEFAULT_JSON = 'data/site-content/default.json';

    /** @var array<string, mixed>|null */
    private ?array $defaults = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /** Заполнить пустые CMS-блоки главной. Возвращает true, если что-то добавлено. */
    public function ensureCoreContent(): bool
    {
        $changed = false;
        if ($this->ensureSettings()) {
            $changed = true;
        }
        if ($this->ensureHero()) {
            $changed = true;
        }
        if ($this->ensureHighlights()) {
            $changed = true;
        }
        if ($changed) {
            $this->em->flush();
        }

        return $changed;
    }

    public function isCoreEmpty(): bool
    {
        $highlightCount = $this->em->getRepository(HomeHighlight::class)->count([]);
        if ($highlightCount > 0) {
            return false;
        }

        $hero = $this->em->getRepository(HomeHero::class)->findOneBy([]);
        if ($hero && trim($hero->getHeadline()) !== '' && trim($hero->getAboutHtml() ?? '') !== '') {
            return false;
        }

        $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([]);
        if ($settings && trim($settings->getDiscountsHtml() ?? '') !== '') {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $home */
    public function applyHeroFromExtract(array $home): HomeHero
    {
        $defaultsHome = $this->homeDefaults();
        $hero = $this->em->getRepository(HomeHero::class)->findOneBy([]) ?? new HomeHero();
        $hero->setEventDates((string) ($home['event_dates'] ?? $defaultsHome['event_dates'] ?? '26 - 28 ИЮНЯ'));
        $hero->setTitleMain((string) ($home['title_main'] ?? ''));
        $hero->setHeadline((string) ($home['title'] ?? 'ХАНУМАН ФЕСТ'));
        $hero->setTitleSecondary((string) ($home['title_secondary'] ?? ''));
        if (!empty($home['content'])) {
            $hero->setAboutHtml((string) $home['content']);
        } elseif (trim($hero->getAboutHtml() ?? '') === '') {
            $hero->setAboutHtml((string) ($defaultsHome['content'] ?? ''));
        }
        $hero->setCtaLabel('Участвовать');
        $hero->setCtaUrl('#register');
        if (!empty($home['title_image_rel'])) {
            $hero->setImagePath('/uploads/wp/'.$home['title_image_rel']);
        } elseif (!$hero->getImagePath() || str_contains((string) $hero->getImagePath(), 'logo-hanuman.png')) {
            $rel = (string) ($defaultsHome['title_image_rel'] ?? '2025/10/2.jpg');
            $hero->setImagePath('/uploads/wp/'.$rel);
        }
        if (!empty($home['promo_video_left'])) {
            $hero->setPromoVideoLeft($this->localUploadUrl($home['promo_video_left']));
        }
        if (!empty($home['promo_video_right'])) {
            $hero->setPromoVideoRight($this->localUploadUrl($home['promo_video_right']));
        }
        $defaultLeft = (string) ($defaultsHome['promo_video_left'] ?? '/uploads/wp/2026/02/IMG_4608.mp4');
        $defaultRight = (string) ($defaultsHome['promo_video_right'] ?? '/uploads/wp/2026/02/IMG_4611.mp4');
        if (!$hero->getPromoVideoLeft()) {
            $hero->setPromoVideoLeft($defaultLeft);
        }
        if (!$hero->getPromoVideoRight()) {
            $hero->setPromoVideoRight($defaultRight);
        }
        $this->em->persist($hero);

        return $hero;
    }

    private function ensureSettings(): bool
    {
        $defaults = $this->settingsDefaults();
        $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([]) ?? new SiteSettings();
        $wasNew = null === $settings->getId();
        $changed = false;

        $defaultLogo = (string) ($defaults['logo_path'] ?? '/uploads/wp/2025/10/logo-hanuman.png');
        $defaultFooterBg = (string) ($defaults['footer_background_path'] ?? '');

        if (trim((string) ($settings->getLogoPath() ?? '')) === '') {
            $settings->setLogoPath($defaultLogo);
            $changed = true;
        }
        if ($defaultFooterBg !== '' && trim((string) ($settings->getFooterBackgroundPath() ?? '')) === '') {
            $settings->setFooterBackgroundPath($defaultFooterBg);
            $changed = true;
        }

        $needsFullFill = $wasNew || trim($settings->getDiscountsHtml() ?? '') === '';
        if (!$needsFullFill) {
            if ($changed) {
                $this->em->persist($settings);
            }

            return $changed;
        }

        $settings->setSiteName((string) ($defaults['site_name'] ?? 'Хануман Фест'));
        $settings->setTagline((string) ($defaults['tagline'] ?? ''));
        $settings->setLogoPath($defaultLogo);
        if ($defaultFooterBg !== '') {
            $settings->setFooterBackgroundPath($defaultFooterBg);
        }
        $settings->setCompanyInfo((string) ($defaults['company_info'] ?? ''));
        $settings->setPhone(isset($defaults['phone']) ? (string) $defaults['phone'] : null);
        $settings->setPhone2(isset($defaults['phone2']) ? (string) $defaults['phone2'] : null);
        $settings->setEmail(isset($defaults['email']) ? (string) $defaults['email'] : 'hanuman-yoga@bk.ru');
        $settings->setVkUrl(isset($defaults['vk_url']) ? (string) $defaults['vk_url'] : null);
        $settings->setTelegramUrl(isset($defaults['telegram_url']) ? (string) $defaults['telegram_url'] : null);
        $settings->setFacebookUrl(isset($defaults['facebook_url']) ? (string) $defaults['facebook_url'] : null);
        $settings->setInstagramUrl(isset($defaults['instagram_url']) ? (string) $defaults['instagram_url'] : null);
        $settings->setDiscountsHtml((string) ($defaults['discounts_html'] ?? ''));
        $settings->setTentNoteHtml((string) ($defaults['tent_note_html'] ?? ''));
        $settings->setCooperationCtaHtml((string) ($defaults['cooperation_cta_html'] ?? ''));
        $this->em->persist($settings);

        return true;
    }

    private function ensureHero(): bool
    {
        $defaultsHome = $this->homeDefaults();
        $hero = $this->em->getRepository(HomeHero::class)->findOneBy([]) ?? new HomeHero();
        $image = (string) ($hero->getImagePath() ?? '');
        $imageMissingOrPlaceholder = $image === '' || str_contains($image, 'logo-hanuman.png');
        $needsFill = null === $hero->getId()
            || trim($hero->getHeadline()) === ''
            || trim($hero->getTitleMain() ?? '') === ''
            || trim($hero->getTitleSecondary() ?? '') === ''
            || trim($hero->getAboutHtml() ?? '') === ''
            || $imageMissingOrPlaceholder
            || !$hero->getPromoVideoLeft()
            || !$hero->getPromoVideoRight();

        if (!$needsFill) {
            return false;
        }

        $this->applyHeroFromExtract([
            'event_dates' => $hero->getEventDates() ?: (string) ($defaultsHome['event_dates'] ?? '26 - 28 ИЮНЯ'),
            'title' => trim($hero->getHeadline()) !== '' ? $hero->getHeadline() : (string) ($defaultsHome['title'] ?? 'ХАНУМАН ФЕСТ'),
            'title_main' => trim($hero->getTitleMain() ?? '') !== ''
                ? $hero->getTitleMain()
                : (string) ($defaultsHome['title_main'] ?? ''),
            'title_secondary' => trim($hero->getTitleSecondary() ?? '') !== ''
                ? $hero->getTitleSecondary()
                : (string) ($defaultsHome['title_secondary'] ?? ''),
            'content' => trim($hero->getAboutHtml() ?? '') !== ''
                ? $hero->getAboutHtml()
                : (string) ($defaultsHome['content'] ?? ''),
            'title_image_rel' => $imageMissingOrPlaceholder
                ? (string) ($defaultsHome['title_image_rel'] ?? '2025/10/2.jpg')
                : null,
            'promo_video_left' => $hero->getPromoVideoLeft()
                ?: (string) ($defaultsHome['promo_video_left'] ?? ''),
            'promo_video_right' => $hero->getPromoVideoRight()
                ?: (string) ($defaultsHome['promo_video_right'] ?? ''),
        ]);

        return true;
    }

    private function ensureHighlights(): bool
    {
        if ($this->em->getRepository(HomeHighlight::class)->count([]) > 0) {
            return false;
        }

        $highlights = $this->highlightDefaults();
        foreach ($highlights['left'] as $i => $row) {
            $tile = new HomeHighlight();
            $tile->setText((string) ($row['text'] ?? ''))
                ->setColumnSide(HomeHighlightColumn::Left)
                ->setStyle($this->parseStyle($row['style'] ?? 'big', HomeHighlightStyle::Big))
                ->setSortOrder($i + 1)
                ->setPublished(true);
            $this->em->persist($tile);
        }
        foreach ($highlights['right'] as $i => $row) {
            $tile = new HomeHighlight();
            $tile->setText((string) ($row['text'] ?? ''))
                ->setColumnSide(HomeHighlightColumn::Right)
                ->setStyle($this->parseStyle($row['style'] ?? 'normal', HomeHighlightStyle::Normal))
                ->setSortOrder($i + 1)
                ->setPublished(true);
            $this->em->persist($tile);
        }

        return $highlights['left'] !== [] || $highlights['right'] !== [];
    }

    /** @return array<string, mixed> */
    private function homeDefaults(): array
    {
        $home = $this->loadDefaults()['home'] ?? [];

        return \is_array($home) ? $home : [];
    }

    /** @return array<string, mixed> */
    private function settingsDefaults(): array
    {
        $settings = $this->loadDefaults()['settings'] ?? [];

        return \is_array($settings) ? $settings : [];
    }

    /**
     * @return array{left: list<array<string, mixed>>, right: list<array<string, mixed>>}
     */
    private function highlightDefaults(): array
    {
        $raw = $this->loadDefaults()['highlights'] ?? [];
        if (!\is_array($raw)) {
            return ['left' => [], 'right' => []];
        }

        $left = $raw['left'] ?? [];
        $right = $raw['right'] ?? [];

        return [
            'left' => \is_array($left) ? array_values($left) : [],
            'right' => \is_array($right) ? array_values($right) : [],
        ];
    }

    /** @return array<string, mixed> */
    private function loadDefaults(): array
    {
        if ($this->defaults !== null) {
            return $this->defaults;
        }

        $path = $this->projectDir.'/'.self::DEFAULT_JSON;
        if (!is_readable($path)) {
            $this->defaults = [];

            return $this->defaults;
        }

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->defaults = \is_array($decoded) ? $decoded : [];

        return $this->defaults;
    }

    private function parseStyle(mixed $value, HomeHighlightStyle $fallback): HomeHighlightStyle
    {
        if (!\is_string($value) || $value === '') {
            return $fallback;
        }

        return HomeHighlightStyle::tryFrom($value) ?? $fallback;
    }

    private function localUploadUrl(mixed $url): ?string
    {
        if (!\is_string($url) || $url === '') {
            return null;
        }
        if (preg_match('#/wp-content/uploads/(.+)$#', $url, $m)) {
            return '/uploads/wp/'.$m[1];
        }

        return str_starts_with($url, '/uploads/') ? $url : '/uploads/wp/'.$url;
    }
}
