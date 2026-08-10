<?php

namespace App\Twig;

use App\Entity\SiteSettings;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class SiteGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getGlobals(): array
    {
        return [
            'settings' => $this->em->getRepository(SiteSettings::class)->findOneBy([], ['id' => 'ASC']),
        ];
    }
}
