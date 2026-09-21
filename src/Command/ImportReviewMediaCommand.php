<?php

namespace App\Command;

use App\Entity\Review;
use App\Entity\ReviewMedia;
use App\Enum\ReviewMediaKind;
use App\Service\Content\ImageOptimizer;
use App\Service\Content\WpContentCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:reviews:import-media',
    description: 'Import gallery photos/videos from WP extract bodies into ReviewMedia',
)]
final class ImportReviewMediaCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageOptimizer $imageOptimizer,
        private readonly WpContentCleaner $wpContentCleaner,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('extract', null, InputOption::VALUE_REQUIRED, 'Path to WP extract JSON', 'var/wp-extract/content.json')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report only')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Re-import even if review already has media')
            ->addOption('fetch', null, InputOption::VALUE_NONE, 'Download missing files from original WP URLs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dry = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');
        $fetch = (bool) $input->getOption('fetch');
        $extractPath = (string) $input->getOption('extract');
        if (!str_starts_with($extractPath, '/')) {
            $extractPath = $this->projectDir.'/'.$extractPath;
        }
        if (!is_file($extractPath)) {
            $io->error('Extract not found: '.$extractPath);

            return Command::FAILURE;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents($extractPath), true, 512, JSON_THROW_ON_ERROR);
        $rows = $data['reviews'] ?? [];
        if (!\is_array($rows) || $rows === []) {
            $io->warning('No reviews in extract.');

            return Command::SUCCESS;
        }

        $destDir = $this->projectDir.'/public/uploads/reviews/media';
        if (!$dry && !is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            $io->error('Cannot create '.$destDir);

            return Command::FAILURE;
        }

        $imported = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $author = trim((string) ($row['author'] ?? ''));
            $body = (string) ($row['body'] ?? '');
            if ($author === '' || $body === '') {
                continue;
            }

            $review = $this->em->getRepository(Review::class)->findOneBy(['authorName' => $author]);
            if (!$review instanceof Review) {
                $io->note('No DB review for author: '.$author);
                ++$skipped;
                continue;
            }

            if (!$force && $review->getMedia()->count() > 0) {
                $io->text('Skip (has media): '.$author);
                ++$skipped;
                continue;
            }

            $urls = $this->extractMediaUrls($body);
            if ($urls === []) {
                $io->text('No media in extract: '.$author);
                ++$skipped;
                continue;
            }

            if ($force && !$dry) {
                foreach ($review->getMedia()->toArray() as $existing) {
                    $review->removeMedia($existing);
                    $this->em->remove($existing);
                }
                $this->em->flush();
            }

            $order = 0;
            $added = 0;
            foreach ($urls as $url) {
                if ($this->isIgnoredExternal($url)) {
                    continue;
                }
                $source = $this->resolveLocalFile($url);
                if (null === $source && $fetch && !$dry) {
                    $source = $this->fetchRemoteToWp($url, $io);
                }
                if (null === $source) {
                    $io->warning('Missing file for '.$author.': '.$url);
                    ++$missing;
                    continue;
                }

                $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
                $kind = $this->isVideoExt($ext) ? ReviewMediaKind::Video : ReviewMediaKind::Image;
                $subdir = $kind === ReviewMediaKind::Video ? 'reviews/video' : 'reviews/media';
                $targetDir = $this->projectDir.'/public/uploads/'.$subdir;
                if (!$dry && !is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                    throw new \RuntimeException('Cannot create '.$targetDir);
                }

                $name = bin2hex(random_bytes(8)).'.'.$ext;
                $publicPath = '/uploads/'.$subdir.'/'.$name;
                $io->text(($dry ? '[dry] ' : '').$author.' ← '.$publicPath.' ('.$kind->value.')');

                if (!$dry) {
                    if (!copy($source, $targetDir.'/'.$name)) {
                        $io->warning('Copy failed: '.$source);
                        ++$missing;
                        continue;
                    }
                    if ($kind === ReviewMediaKind::Image) {
                        try {
                            $this->imageOptimizer->optimizePublicPath($publicPath);
                        } catch (\Throwable $e) {
                            $io->warning('Optimize failed: '.$e->getMessage());
                        }
                    }

                    $media = new ReviewMedia();
                    $media->setKind($kind);
                    $media->setPath($publicPath);
                    $media->setSortOrder(++$order);
                    $media->setPublished(true);
                    $review->addMedia($media);
                    $this->em->persist($media);
                }
                ++$added;
            }

            if (!$dry && $added > 0) {
                // Keep portrait; clean leftover markup from body if any
                $cleaned = $this->wpContentCleaner->cleanHtml($review->getBody()) ?? '';
                $review->setBody($cleaned);
            }

            $imported += $added;
        }

        if (!$dry) {
            $this->em->flush();
        }

        $io->success(sprintf(
            'Done. media=%d skipped=%d missing=%d%s',
            $imported,
            $skipped,
            $missing,
            $dry ? ' (dry-run)' : '',
        ));

        return $missing > 0 && $imported === 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function extractMediaUrls(string $html): array
    {
        $urls = [];
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/iu', $html, $m)) {
            foreach ($m[1] as $src) {
                $urls[] = $src;
            }
        }
        if (preg_match_all('/<(?:video|source)[^>]+src=["\']([^"\']+)["\']/iu', $html, $m2)) {
            foreach ($m2[1] as $src) {
                $urls[] = $src;
            }
        }

        $unique = [];
        foreach ($urls as $url) {
            $key = $this->normalizeUrlKey($url);
            if ($key !== '' && !isset($unique[$key])) {
                $unique[$key] = $url;
            }
        }

        return array_values($unique);
    }

    private function normalizeUrlKey(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $path = preg_replace('#-\d+x\d+(\.[a-z0-9]+)$#i', '$1', $path) ?? $path;

        return strtolower($path);
    }

    private function isIgnoredExternal(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

        return str_contains($host, 'vk.com')
            || str_contains($url, '/emoji/')
            || str_contains($url, 'data:image');
    }

    private function fetchRemoteToWp(string $url, SymfonyStyle $io): ?string
    {
        if (!preg_match('#/wp-content/uploads/(.+)$#i', $url, $m)) {
            return null;
        }
        $rel = rawurldecode($m[1]);
        // Prefer original (non -WxH) name on disk
        $relStore = preg_replace('#-\d+x\d+(\.[a-z0-9]+)$#i', '$1', $rel) ?? $rel;
        $target = $this->projectDir.'/public/uploads/wp/'.$relStore;
        $dir = \dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        if (is_file($target)) {
            return $target;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: HanumanFestImporter/1.0\r\nAccept: image/*,video/*,*/*\r\n",
                'timeout' => 30,
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if (false === $data || $data === '') {
            $io->warning('Fetch failed: '.$url);

            return null;
        }
        if (false === file_put_contents($target, $data)) {
            return null;
        }
        $io->text('Fetched → /uploads/wp/'.$relStore);

        return $target;
    }

    private function resolveLocalFile(string $url): ?string
    {
        $rel = null;
        if (preg_match('#/wp-content/uploads/(.+)$#i', $url, $m)) {
            $rel = $m[1];
        } elseif (preg_match('#/uploads/wp/(.+)$#i', $url, $m)) {
            $rel = $m[1];
        } elseif (preg_match('#^/uploads/(.+)$#i', $url, $m)) {
            $candidate = $this->projectDir.'/public/uploads/'.$m[1];
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        if (null === $rel) {
            return null;
        }

        $rel = rawurldecode($rel);
        $relFull = preg_replace('#-\d+x\d+(\.[a-z0-9]+)$#i', '$1', $rel) ?? $rel;
        $candidates = array_unique([
            $this->projectDir.'/public/uploads/wp/'.$relFull,
            $this->projectDir.'/public/uploads/wp/'.$rel,
            $this->projectDir.'/var/wp-extract/uploads/'.$relFull,
            $this->projectDir.'/var/wp-extract/uploads/'.$rel,
        ]);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Fuzzy: same directory, filename prefix match
        $dir = $this->projectDir.'/public/uploads/wp/'.\dirname($relFull);
        $prefix = substr(basename($relFull), 0, 40);
        if (is_dir($dir) && $prefix !== '') {
            $matches = glob($dir.'/'.$prefix.'*') ?: [];
            foreach ($matches as $match) {
                if (is_file($match)) {
                    return $match;
                }
            }
        }

        return null;
    }

    private function isVideoExt(string $ext): bool
    {
        return in_array(strtolower($ext), ['mp4', 'webm', 'mov', 'm4v'], true);
    }
}
