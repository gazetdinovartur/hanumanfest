<?php

namespace App\Repository;

use App\Entity\HomeHighlight;
use App\Enum\HomeHighlightColumn;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomeHighlight>
 */
final class HomeHighlightRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeHighlight::class);
    }

    /** @return list<HomeHighlight> */
    public function findOrderedByColumn(HomeHighlightColumn $column): array
    {
        return $this->findBy(
            ['columnSide' => $column],
            ['sortOrder' => 'ASC', 'id' => 'ASC'],
        );
    }

    public function nextSortOrder(HomeHighlightColumn $column): int
    {
        $max = $this->createQueryBuilder('h')
            ->select('MAX(h.sortOrder)')
            ->andWhere('h.columnSide = :column')
            ->setParameter('column', $column)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }
}
