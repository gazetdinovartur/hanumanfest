<?php

namespace App\Command;

use App\Entity\GalleryItem;
use App\Entity\HomeHero;
use App\Entity\InfoBlock;
use App\Entity\Person;
use App\Entity\Review;
use App\Entity\ReviewMedia;
use App\Entity\SiteSettings;
use App\Enum\ReviewMediaKind;
use App\Service\Content\ImageOptimizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:images:optimize',
    description: 'Generate WebP variants; relocate /uploads/wp/ paths into CMS upload dirs',
)]
final class OptimizeImagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageOptimizer $optimizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would change')
            ->addOption('relocate-wp', null, InputOption::VALUE_NONE, 'Copy WP paths into target dirs and update DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dry = (bool) $input->getOption('dry-run');
        $relocate = (bool) $input->getOption('relocate-wp');

        try {
            $this->optimizer->assertAvailable();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $jobs = [];

        foreach ($this->em->getRepository(GalleryItem::class)->findAll() as $item) {
            $jobs[] = ['label' => 'gallery#'.$item->getId(), 'get' => fn () => $item->getImagePath(), 'set' => function (string $p) use ($item): void {
                $item->setImagePath($p);
            }, 'subdir' => 'gallery', 'image' => true];
        }
        foreach ($this->em->getRepository(Review::class)->findAll() as $item) {
            $jobs[] = ['label' => 'review-photo#'.$item->getId(), 'get' => fn () => $item->getPhotoPath(), 'set' => function (?string $p) use ($item): void {
                $item->setPhotoPath($p);
            }, 'subdir' => 'reviews', 'image' => true];
        }
        foreach ($this->em->getRepository(ReviewMedia::class)->findAll() as $item) {
            $jobs[] = ['label' => 'review-media#'.$item->getId(), 'get' => fn () => $item->getPath(), 'set' => function (string $p) use ($item): void {
                $item->setPath($p);
            }, 'subdir' => $item->getKind() === ReviewMediaKind::Video ? 'reviews/video' : 'reviews/media', 'image' => $item->getKind() === ReviewMediaKind::Image];
        }
        foreach ($this->em->getRepository(Person::class)->findAll() as $item) {
            $jobs[] = ['label' => 'person#'.$item->getId(), 'get' => fn () => $item->getPhotoPath(), 'set' => function (?string $p) use ($item): void {
                $item->setPhotoPath($p);
            }, 'subdir' => 'people', 'image' => true];
        }
        foreach ($this->em->getRepository(InfoBlock::class)->findAll() as $item) {
            $jobs[] = ['label' => 'info#'.$item->getId(), 'get' => fn () => $item->getImagePath(), 'set' => function (?string $p) use ($item): void {
                $item->setImagePath($p);
            }, 'subdir' => 'info', 'image' => true];
        }
        foreach ($this->em->getRepository(HomeHero::class)->findAll() as $item) {
            $jobs[] = ['label' => 'hero#'.$item->getId(), 'get' => fn () => $item->getImagePath(), 'set' => function (?string $p) use ($item): void {
                $item->setImagePath($p);
            }, 'subdir' => 'hero', 'image' => true];
        }
        foreach ($this->em->getRepository(SiteSettings::class)->findAll() as $item) {
            $jobs[] = ['label' => 'settings-logo#'.$item->getId(), 'get' => fn () => $item->getLogoPath(), 'set' => function (?string $p) use ($item): void {
                $item->setLogoPath($p);
            }, 'subdir' => 'site', 'image' => true];
            $jobs[] = ['label' => 'settings-footer#'.$item->getId(), 'get' => fn () => $item->getFooterBackgroundPath(), 'set' => function (?string $p) use ($item): void {
                $item->setFooterBackgroundPath($p);
            }, 'subdir' => 'site', 'image' => true];
        }

        $ok = 0;
        $skip = 0;
        $fail = 0;

        foreach ($jobs as $job) {
            $path = $job['get']();
            if (!\is_string($path) || $path === '') {
                ++$skip;
                continue;
            }
            if (!$job['image']) {
                ++$skip;
                continue;
            }

            try {
                $isWp = str_contains($path, '/uploads/wp/');
                if ($isWp && $relocate) {
                    $io->text(($dry ? '[dry] ' : '').'Relocate '.$job['label'].': '.$path);
                    if (!$dry) {
                        $newPath = $this->optimizer->relocateFromWp($path, $job['subdir']);
                        $job['set']($newPath);
                    }
                    ++$ok;
                    continue;
                }

                if ($isWp && !$relocate) {
                    $io->note($job['label'].' still on WP path — run with --relocate-wp');
                    ++$skip;
                    continue;
                }

                $io->text(($dry ? '[dry] ' : '').'Optimize '.$job['label'].': '.$path);
                if (!$dry) {
                    $this->optimizer->optimizePublicPath($path);
                }
                ++$ok;
            } catch (\Throwable $e) {
                $io->warning($job['label'].': '.$e->getMessage());
                ++$fail;
            }
        }

        if (!$dry) {
            $this->em->flush();
        }

        $io->success(sprintf('Done. ok=%d skip=%d fail=%d%s', $ok, $skip, $fail, $dry ? ' (dry-run)' : ''));

        return $fail > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
