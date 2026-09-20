<?php

namespace App\Command;

use App\Entity\SitePage;
use App\Enum\SitePageTemplate;
use App\Service\Content\WpContentCleaner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:site-pages', description: 'Seed CMS pages (legal + kitchen) from data/site-pages')]
class SeedSitePagesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WpContentCleaner $wpContentCleaner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dir = dirname(__DIR__, 2).'/data/site-pages';
        if (!is_dir($dir)) {
            $io->error('Directory not found: '.$dir);

            return Command::FAILURE;
        }

        $defs = [
            [
                'slug' => 'политика-возвратов',
                'title' => 'Политика возвратов',
                'file' => 'политика-возвратов.html',
                'template' => SitePageTemplate::Default,
                'footer' => true,
                'sort' => 10,
            ],
            [
                'slug' => 'публичная-оферта',
                'title' => 'Публичная оферта',
                'file' => 'публичная-оферта.html',
                'template' => SitePageTemplate::Default,
                'footer' => true,
                'sort' => 20,
            ],
            [
                'slug' => 'политика-конфиденциальности',
                'title' => 'Политика конфиденциальности',
                'file' => 'политика-конфиденциальности.html',
                'template' => SitePageTemplate::Default,
                'footer' => true,
                'sort' => 30,
            ],
            [
                'slug' => 'питание-на-хануман-фест',
                'title' => 'Питание на Хануман Фест',
                'file' => 'питание-на-хануман-фест.html',
                'template' => SitePageTemplate::Kitchen,
                'footer' => false,
                'sort' => 100,
            ],
        ];

        foreach ($defs as $def) {
            $path = $dir.'/'.$def['file'];
            if (!is_readable($path)) {
                $io->warning('Skip missing file: '.$path);
                continue;
            }
            $html = $this->wpContentCleaner->cleanHtml((string) file_get_contents($path)) ?? '';
            $page = $this->em->getRepository(SitePage::class)->findOneBy(['slug' => $def['slug']]) ?? new SitePage();
            $page->setSlug($def['slug'])
                ->setTitle($def['title'])
                ->setContentHtml($html)
                ->setTemplate($def['template'])
                ->setShowInFooter($def['footer'])
                ->setSortOrder($def['sort'])
                ->setPublished(true);
            if ($def['template'] === SitePageTemplate::Kitchen) {
                $page->setKitchenVideo1('/uploads/wp/2026/03/IMG_8652.mp4')
                    ->setKitchenVideo2('/uploads/wp/2026/03/IMG_8223.mp4')
                    ->setKitchenVideo3('/uploads/wp/2026/03/IMG_8224.mp4')
                    ->setKitchenVideo4('/uploads/wp/2026/03/IMG_8222.mp4');
            }
            $this->em->persist($page);
        }

        $this->em->flush();
        $io->success(sprintf('Seeded %d site pages.', count($defs)));

        return Command::SUCCESS;
    }
}
