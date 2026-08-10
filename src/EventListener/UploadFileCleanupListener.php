<?php

namespace App\EventListener;

use App\Entity\GalleryItem;
use App\Entity\HomeHero;
use App\Entity\InfoBlock;
use App\Entity\Person;
use App\Entity\Review;
use App\Entity\SiteSettings;
use App\Service\Content\UploadFileRemover;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class UploadFileCleanupListener
{
    public function __construct(
        private readonly UploadFileRemover $uploadFileRemover,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        match (true) {
            $entity instanceof GalleryItem => $this->uploadFileRemover->deletePublicPath($entity->getImagePath()),
            $entity instanceof Person => $this->uploadFileRemover->deletePublicPath($entity->getPhotoPath()),
            $entity instanceof Review => $this->uploadFileRemover->deletePublicPath($entity->getPhotoPath()),
            $entity instanceof InfoBlock => $this->uploadFileRemover->deletePublicPath($entity->getImagePath()),
            $entity instanceof HomeHero => $this->removeHomeHeroFiles($entity),
            default => null,
        };
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        match (true) {
            $entity instanceof SiteSettings => $this->updateSiteSettingsFiles($args),
            $entity instanceof GalleryItem => $this->deleteChangedPath($args, 'imagePath'),
            $entity instanceof Person => $this->deleteChangedPath($args, 'photoPath'),
            $entity instanceof Review => $this->deleteChangedPath($args, 'photoPath'),
            $entity instanceof InfoBlock => $this->deleteChangedPath($args, 'imagePath'),
            $entity instanceof HomeHero => $this->updateHomeHeroFiles($args),
            default => null,
        };
    }

    private function removeHomeHeroFiles(HomeHero $hero): void
    {
        $this->uploadFileRemover->deletePublicPath($hero->getImagePath());
        $this->uploadFileRemover->deletePublicPath($hero->getPromoVideoLeft());
        $this->uploadFileRemover->deletePublicPath($hero->getPromoVideoRight());
    }

    private function updateHomeHeroFiles(PreUpdateEventArgs $args): void
    {
        $this->deleteChangedPath($args, 'imagePath');
        $this->deleteChangedPath($args, 'promoVideoLeft');
        $this->deleteChangedPath($args, 'promoVideoRight');
    }

    private function updateSiteSettingsFiles(PreUpdateEventArgs $args): void
    {
        $this->deleteChangedPath($args, 'logoPath');
        $this->deleteChangedPath($args, 'footerBackgroundPath');
    }

    private function deleteChangedPath(PreUpdateEventArgs $args, string $field): void
    {
        $changeSet = $args->getEntityChangeSet();
        if (!isset($changeSet[$field])) {
            return;
        }

        [$old, $new] = $changeSet[$field];
        if (!\is_string($old) || '' === $old || $old === $new) {
            return;
        }

        $this->uploadFileRemover->deletePublicPath($old);
    }
}
