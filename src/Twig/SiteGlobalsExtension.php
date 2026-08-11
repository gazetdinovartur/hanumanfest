<?php

namespace App\Twig;

use App\Entity\SiteSettings;
use App\Repository\SitePageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class SiteGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SitePageRepository $sitePageRepository,
    ) {
    }

    public function getGlobals(): array
    {
        $footerPages = [];
        try {
            $footerPages = $this->sitePageRepository->findFooterPages();
        } catch (\Throwable) {
            // Schema may be mid-migration in tests/deploy.
        }

        return [
            'settings' => $this->em->getRepository(SiteSettings::class)->findOneBy([], ['id' => 'ASC']),
            'footerPages' => $footerPages,
        ];
    }
}
