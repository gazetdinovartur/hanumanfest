<?php

namespace App\EventListener;

use App\Entity\GalleryItem;
use App\Entity\HomeHero;
use App\Entity\InfoBlock;
use App\Entity\Person;
use App\Entity\Review;
use App\Entity\ReviewMedia;
use App\Entity\SitePage;
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
            $entity instanceof ReviewMedia => $this->uploadFileRemover->deletePublicPath($entity->getPath()),
            $entity instanceof InfoBlock => $this->uploadFileRemover->deletePublicPath($entity->getImagePath()),
            $entity instanceof HomeHero => $this->removeHomeHeroFiles($entity),
            $entity instanceof SitePage => $this->removeSitePageFiles($entity),
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
            $entity instanceof ReviewMedia => $this->deleteChangedPath($args, 'path'),
            $entity instanceof InfoBlock => $this->deleteChangedPath($args, 'imagePath'),
            $entity instanceof HomeHero => $this->updateHomeHeroFiles($args),
            $entity instanceof SitePage => $this->updateSitePageFiles($args),
            default => null,
        };
    }

    private function removeHomeHeroFiles(HomeHero $hero): void
    {
        $this->uploadFileRemover->deletePublicPath($hero->getImagePath());
        $this->uploadFileRemover->deletePublicPath($hero->getPromoVideoLeft());
        $this->uploadFileRemover->deletePublicPath($hero->getPromoVideoRight());
    }

    private function removeSitePageFiles(SitePage $page): void
    {
        foreach ($page->getKitchenVideos() as $videoPath) {
            $this->uploadFileRemover->deletePublicPath($videoPath);
        }
    }

    private function updateHomeHeroFiles(PreUpdateEventArgs $args): void
    {
        $this->deleteChangedPath($args, 'imagePath');
        $this->deleteChangedPath($args, 'promoVideoLeft');
        $this->deleteChangedPath($args, 'promoVideoRight');
    }

    private function updateSitePageFiles(PreUpdateEventArgs $args): void
    {
        $this->deleteChangedPath($args, 'kitchenVideo1');
        $this->deleteChangedPath($args, 'kitchenVideo2');
        $this->deleteChangedPath($args, 'kitchenVideo3');
        $this->deleteChangedPath($args, 'kitchenVideo4');
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
