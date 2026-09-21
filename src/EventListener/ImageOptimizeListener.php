<?php

namespace App\EventListener;

use App\Entity\GalleryItem;
use App\Entity\HomeHero;
use App\Entity\InfoBlock;
use App\Entity\Person;
use App\Entity\Review;
use App\Entity\ReviewMedia;
use App\Entity\SiteSettings;
use App\Enum\ReviewMediaKind;
use App\Service\Content\ImageOptimizer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * Optimizes newly uploaded CMS images (skips videos and missing files).
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
final class ImageOptimizeListener
{
    public function __construct(
        private readonly ImageOptimizer $imageOptimizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->optimizeEntity($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->optimizeEntity($args->getObject());
    }

    private function optimizeEntity(object $entity): void
    {
        $paths = match (true) {
            $entity instanceof Review => array_filter([$entity->getPhotoPath()]),
            $entity instanceof Person => array_filter([$entity->getPhotoPath()]),
            $entity instanceof GalleryItem => array_filter([$entity->getImagePath()]),
            $entity instanceof InfoBlock => array_filter([$entity->getImagePath()]),
            $entity instanceof HomeHero => array_filter([$entity->getImagePath()]),
            $entity instanceof SiteSettings => array_filter([
                $entity->getLogoPath(),
                $entity->getFooterBackgroundPath(),
            ]),
            $entity instanceof ReviewMedia => $entity->getKind() === ReviewMediaKind::Image
                ? array_filter([$entity->getPath()])
                : [],
            default => [],
        };

        foreach ($paths as $path) {
            if (!\is_string($path) || $path === '') {
                continue;
            }
            if (str_contains($path, '/uploads/wp/')) {
                continue;
            }
            try {
                $this->imageOptimizer->optimizePublicPath($path);
            } catch (\Throwable $e) {
                $this->logger->warning('Image optimize failed: {path} — {message}', [
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
