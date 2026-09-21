<?php

namespace App\Repository;

use App\Entity\GalleryItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GalleryItem>
 */
final class GalleryItemRepository extends ServiceEntityRepository
{
    public const HOME_LIMIT = 12;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryItem::class);
    }

    /** @return list<GalleryItem> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['sortOrder' => 'ASC', 'id' => 'ASC']);
    }

    /** @return list<GalleryItem> */
    public function findPublishedOrdered(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.published = true')
            ->orderBy('g.sortOrder', 'ASC')
            ->addOrderBy('g.id', 'ASC');
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.published = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<int> */
    public function findOrderedIds(): array
    {
        $rows = $this->createQueryBuilder('g')
            ->select('g.id')
            ->orderBy('g.sortOrder', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
