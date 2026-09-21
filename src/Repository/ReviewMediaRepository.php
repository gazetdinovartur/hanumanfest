<?php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\ReviewMedia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReviewMedia>
 */
final class ReviewMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewMedia::class);
    }

    /** @return list<ReviewMedia> */
    public function findForReviewOrdered(Review $review): array
    {
        return $this->findBy(['review' => $review], ['sortOrder' => 'ASC', 'id' => 'ASC']);
    }
}
