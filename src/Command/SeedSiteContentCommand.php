<?php

namespace App\Command;

use App\Entity\FaqItem;
use App\Entity\GalleryItem;
use App\Entity\HomeHero;
use App\Entity\InfoBlock;
use App\Entity\Person;
use App\Entity\Review;
use App\Entity\SiteSettings;
use App\Enum\PersonKind;
use App\Service\Content\WpContentCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:site-content', description: 'Seed homepage CMS from WP extract (var/wp-extract/content.json)')]
class SeedSiteContentCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WpContentCleaner $wpContentCleaner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_REQUIRED, 'Path to content.json', 'var/wp-extract/content.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = (string) $input->getOption('json');
        if (!is_readable($path)) {
            $path = dirname(__DIR__, 2).'/'.ltrim($path, '/');
        }
        if (!is_readable($path)) {
            $io->error('content.json not found: '.$path);

            return Command::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $home = $data['home'] ?? [];

        $this->purge(Person::class);
        $this->purge(GalleryItem::class);
        $this->purge(FaqItem::class);
        $this->purge(InfoBlock::class);
        $this->purge(Review::class);

        $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([]) ?? new SiteSettings();
        $settings->setSiteName('Хануман Фест');
        $settings->setTagline('Море йоги, музыки и творчества в экологически чистом месте.');
        $settings->setLogoPath('/uploads/wp/2025/10/logo-hanuman.png');
        $settings->setFooterBackgroundPath('/uploads/wp/2025/10/KxUveqx2_ySkKL1uodMLRlPX-AHveG67FW4hOk7tRbAIMeBeq5k8Rhc_QzrWH5dek6rzlxxbjUq7lY9XEafi9XBy.jpg');
        $settings->setCompanyInfo("ИП Сараев Антон Валерьевич\n\nОГРНИП 304662518300032\nИНН 662504951300");
        $settings->setContactsHtml("+7 (343) 385-83-70\n+7 922 211 61 18\nhanumanfest@gmail.com\nhttps://vk.com/hanumanyoga\nhttps://www.facebook.com/hanumanyoga.ru/\nhttps://www.instagram.com/hanuman_yoga.ru/\nhttps://t.me/Hanuman_ekb");
        $settings->setVkUrl('https://vk.com/hanumanyoga');
        $settings->setTelegramUrl('https://t.me/Hanuman_ekb');
        $settings->setNotificationEmail('hanumanfest@gmail.com');
        $this->em->persist($settings);

        $hero = $this->em->getRepository(HomeHero::class)->findOneBy([]) ?? new HomeHero();
        $hero->setEventDates((string) ($home['event_dates'] ?? '26 - 28 ИЮНЯ'));
        $hero->setTitleMain((string) ($home['title_main'] ?? ''));
        $hero->setHeadline((string) ($home['title'] ?? 'ХАНУМАН ФЕСТ'));
        $hero->setTitleSecondary((string) ($home['title_secondary'] ?? ''));
        $hero->setAboutHtml($this->wpContentCleaner->cleanHtml((string) ($home['content'] ?? '')));
        $hero->setCtaLabel('Участвовать');
        $hero->setCtaUrl('#register');
        if (!empty($home['title_image_rel'])) {
            $hero->setImagePath('/uploads/wp/'.$home['title_image_rel']);
        }
        $hero->setPromoVideoLeft($this->localUploadUrl($home['promo_video_left'] ?? null));
        $hero->setPromoVideoRight($this->localUploadUrl($home['promo_video_right'] ?? null));
        $this->em->persist($hero);

        $kindMap = [
            'guest' => PersonKind::Guest,
            'musician' => PersonKind::Musician,
            'master' => PersonKind::Master,
        ];
        foreach ($data['people'] ?? [] as $i => $row) {
            $kind = $kindMap[$row['kind'] ?? ''] ?? null;
            if (!$kind) {
                continue;
            }
            $bio = $this->wpContentCleaner->cleanHtml((string) ($row['bio'] ?? ''));
            $excerpt = trim((string) ($row['excerpt'] ?? ''));
            if ($excerpt === '') {
                $excerpt = $this->trimWords(strip_tags($bio ?? ''), $kind === PersonKind::Musician ? 41 : 40);
            } else {
                $excerpt = $this->wpContentCleaner->cleanPlain($excerpt) ?? '';
            }
            $p = new Person();
            $p->setKind($kind)
                ->setName((string) $row['name'])
                ->setExcerpt($excerpt)
                ->setBio($bio)
                ->setSortOrder((int) ($row['sort'] ?? $i))
                ->setPublished(true);
            if (!empty($row['photo_rel'])) {
                $p->setPhotoPath('/uploads/wp/'.$row['photo_rel']);
            }
            $this->em->persist($p);
        }

        foreach ($data['gallery_rels'] ?? [] as $i => $rel) {
            $item = new GalleryItem();
            $item->setImagePath('/uploads/wp/'.$rel)->setSortOrder($i + 1)->setPublished(true);
            $this->em->persist($item);
        }

        foreach ($data['faqs'] ?? [] as $i => $row) {
            $faq = new FaqItem();
            $faq->setQuestion((string) $row['question'])
                ->setAnswer($this->wpContentCleaner->cleanHtml((string) $row['answer']))
                ->setSortOrder((int) ($row['sort'] ?? $i))
                ->setPublished(true);
            $this->em->persist($faq);
        }

        foreach ($data['info_blocks'] ?? [] as $i => $row) {
            $block = new InfoBlock();
            $block->setTitle((string) $row['title'])
                ->setContent($this->wpContentCleaner->cleanHtml((string) ($row['content'] ?? '')))
                ->setSortOrder((int) ($row['sort'] ?? $i))
                ->setPublished(true);
            if (!empty($row['image_rel'])) {
                $block->setImagePath('/uploads/wp/'.$row['image_rel']);
            }
            $this->em->persist($block);
        }

        foreach ($data['reviews'] ?? [] as $i => $row) {
            $review = new Review();
            $review->setAuthorName((string) $row['author'])
                ->setBody($this->wpContentCleaner->cleanHtml((string) $row['body']))
                ->setSortOrder((int) ($row['sort'] ?? $i))
                ->setPublished(true);
            if (!empty($row['photo_rel'])) {
                $review->setPhotoPath('/uploads/wp/'.$row['photo_rel']);
            }
            $this->em->persist($review);
        }

        $this->em->flush();
        $io->success(sprintf(
            'WP content imported: %d people, %d gallery, %d faqs, %d info, %d reviews.',
            count($data['people'] ?? []),
            count($data['gallery_rels'] ?? []),
            count($data['faqs'] ?? []),
            count($data['info_blocks'] ?? []),
            count($data['reviews'] ?? []),
        ));

        return Command::SUCCESS;
    }

    private function purge(string $entityClass): void
    {
        foreach ($this->em->getRepository($entityClass)->findAll() as $entity) {
            $this->em->remove($entity);
        }
        $this->em->flush();
    }

    private function localUploadUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        if (preg_match('#/wp-content/uploads/(.+)$#', $url, $m)) {
            return '/uploads/wp/'.$m[1];
        }

        return $url;
    }

    private function trimWords(string $text, int $words): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
        $parts = preg_split('/\s+/u', $text) ?: [];
        if (count($parts) <= $words) {
            return $text;
        }

        return implode(' ', array_slice($parts, 0, $words)).'…';
    }
}
