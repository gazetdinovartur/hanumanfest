<?php

namespace App\Command;

use App\Entity\FaqItem;
use App\Entity\GalleryItem;
use App\Entity\HomeHighlight;
use App\Entity\InfoBlock;
use App\Entity\Person;
use App\Entity\Review;
use App\Enum\PersonKind;
use App\Service\Content\SiteContentDefaults;
use App\Service\Content\WpContentCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:site-content',
    description: 'Seed homepage CMS (defaults in repo; optional WP extract JSON for people/gallery/FAQ)',
)]
class SeedSiteContentCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WpContentCleaner $wpContentCleaner,
        private readonly SiteContentDefaults $siteContentDefaults,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'Path to content.json (WP extract)')
            ->addOption('if-empty', null, InputOption::VALUE_NONE, 'Skip if core CMS blocks already filled')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Replace people/gallery/FAQ/reviews/info blocks from JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('if-empty') && !$this->siteContentDefaults->isCoreEmpty()) {
            $io->note('Core homepage CMS already has content — skipped. Use --force with JSON for full WP re-import.');

            return Command::SUCCESS;
        }

        $path = $this->resolveJsonPath($input->getOption('json'));
        /** @var array<string, mixed> $data */
        $data = [];
        if ($path !== null) {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $io->note('Using content JSON: '.$path);
        } else {
            $io->note('No content JSON found — SiteContentDefaults will load '.SiteContentDefaults::DEFAULT_JSON.' if present.');
        }

        $home = $data['home'] ?? [];
        if ($home !== []) {
            $home['content'] = $this->wpContentCleaner->cleanHtml((string) ($home['content'] ?? ''));
            $this->siteContentDefaults->applyHeroFromExtract($home);
        }

        $this->siteContentDefaults->ensureCoreContent();

        if ($input->getOption('force')) {
            $this->purge(Person::class);
            $this->purge(GalleryItem::class);
            $this->purge(FaqItem::class);
            $this->purge(InfoBlock::class);
            $this->purge(Review::class);
            $this->importLists($data);
        }

        $this->em->flush();
        $io->success(sprintf(
            'Site content ready: %d people, %d gallery, %d faqs, %d info, %d reviews, %d highlights.',
            $this->em->getRepository(Person::class)->count([]),
            $this->em->getRepository(GalleryItem::class)->count([]),
            $this->em->getRepository(FaqItem::class)->count([]),
            $this->em->getRepository(InfoBlock::class)->count([]),
            $this->em->getRepository(Review::class)->count([]),
            $this->em->getRepository(HomeHighlight::class)->count([]),
        ));

        return Command::SUCCESS;
    }

    /** @param array<string, mixed> $data */
    private function importLists(array $data): void
    {
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
                ->setAnswer($this->wpContentCleaner->cleanHtml((string) ($row['answer'])))
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
                ->setBody($this->wpContentCleaner->cleanHtml((string) ($row['body'])))
                ->setSortOrder((int) ($row['sort'] ?? $i))
                ->setPublished(true);
            if (!empty($row['photo_rel'])) {
                $review->setPhotoPath('/uploads/wp/'.$row['photo_rel']);
            }
            $this->em->persist($review);
        }
    }

    private function resolveJsonPath(?string $requested): ?string
    {
        $candidates = array_filter([
            $requested,
            SiteContentDefaults::DEFAULT_JSON,
            'var/wp-extract/content.json',
        ]);
        $root = dirname(__DIR__, 2);
        foreach ($candidates as $candidate) {
            $path = str_starts_with((string) $candidate, '/') ? $candidate : $root.'/'.ltrim((string) $candidate, '/');
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function purge(string $entityClass): void
    {
        foreach ($this->em->getRepository($entityClass)->findAll() as $entity) {
            $this->em->remove($entity);
        }
        $this->em->flush();
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
