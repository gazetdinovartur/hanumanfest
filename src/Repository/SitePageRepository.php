<?php

namespace App\Repository;

use App\Entity\SitePage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SitePage>
 */
class SitePageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SitePage::class);
    }

    public function findPublishedBySlug(string $slug): ?SitePage
    {
        return $this->findOneBy([
            'slug' => trim($slug, '/'),
            'published' => true,
        ]);
    }

    /**
     * @return list<SitePage>
     */
    public function findFooterPages(): array
    {
        return $this->findBy(
            ['published' => true, 'showInFooter' => true],
            ['sortOrder' => 'ASC', 'id' => 'ASC'],
        );
    }
}
