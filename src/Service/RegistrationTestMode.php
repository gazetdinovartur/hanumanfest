<?php

namespace App\Service;

use App\Entity\SiteSettings;
use Doctrine\ORM\EntityManagerInterface;

final class RegistrationTestMode
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function isEnabled(): bool
    {
        try {
            $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([], ['id' => 'ASC']);

            return $settings?->isRegistrationTestMode() ?? false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function setEnabled(bool $enabled): void
    {
        $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([], ['id' => 'ASC']);
        if ($settings === null) {
            $settings = new SiteSettings();
            $this->em->persist($settings);
        }

        $settings->setRegistrationTestMode($enabled);
        $this->em->flush();
    }
}
