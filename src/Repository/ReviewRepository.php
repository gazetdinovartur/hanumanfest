<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
final class ReviewRepository extends ServiceEntityRepository
{
    public const HOME_LIMIT = 6;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /** @return list<Review> */
    public function findPublishedForHome(int $limit = self::HOME_LIMIT): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.published = true')
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Review> */
    public function findAllPublished(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.published = true')
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countPublished(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.published = true')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
