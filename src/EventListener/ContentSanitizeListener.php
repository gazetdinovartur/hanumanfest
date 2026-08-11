<?php

namespace App\EventListener;

use App\Entity\FaqItem;
use App\Entity\HomeHero;
use App\Entity\InfoBlock;
use App\Entity\Person;
use App\Entity\Review;
use App\Entity\SitePage;
use App\Service\Content\WpContentCleaner;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class ContentSanitizeListener
{
    public function __construct(
        private readonly WpContentCleaner $wpContentCleaner,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->sanitize($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->sanitize($args->getObject());
    }

    private function sanitize(object $entity): void
    {
        match (true) {
            $entity instanceof HomeHero => $entity->setAboutHtml(
                $this->wpContentCleaner->cleanHtml($entity->getAboutHtml()),
            ),
            $entity instanceof Person => $this->sanitizePerson($entity),
            $entity instanceof InfoBlock => $entity->setContent(
                $this->wpContentCleaner->cleanHtml($entity->getContent()),
            ),
            $entity instanceof FaqItem => $entity->setAnswer(
                $this->wpContentCleaner->cleanHtml($entity->getAnswer()),
            ),
            $entity instanceof Review => $entity->setBody(
                $this->wpContentCleaner->cleanHtml($entity->getBody()),
            ),
            $entity instanceof SitePage => $entity->setContentHtml(
                $this->wpContentCleaner->cleanHtml($entity->getContentHtml()) ?? '',
            ),
            default => null,
        };
    }

    private function sanitizePerson(Person $person): void
    {
        $person->setExcerpt($this->wpContentCleaner->cleanPlain($person->getExcerpt()));
        $person->setBio($this->wpContentCleaner->cleanHtml($person->getBio()));
    }
}
