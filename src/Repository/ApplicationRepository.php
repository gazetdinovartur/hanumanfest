<?php

namespace App\Repository;

use App\Entity\Application;
use App\Entity\FestivalSeason;
use App\Entity\Payment;
use App\Entity\Product;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Application>
 */
class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    public function findOneByUuid(Uuid|string $uuid): ?Application
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    public function findActiveDuplicateByEmail(string $email, Product $product, FestivalSeason $season, bool $isTest = false): ?Application
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->andWhere('LOWER(u.email) = :email')
            ->andWhere('a.product = :product')
            ->andWhere('a.season = :season')
            ->andWhere('a.isTest = :isTest')
            ->andWhere('a.status NOT IN (:inactive)')
            ->setParameter('email', mb_strtolower($email))
            ->setParameter('product', $product)
            ->setParameter('season', $season)
            ->setParameter('isTest', $isTest)
            ->setParameter('inactive', [ApplicationStatus::Cancelled, ApplicationStatus::Refunded])
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPartiallyPaidByEmail(string $email, bool $isTest = false): ?Application
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.user', 'u')
            ->andWhere('LOWER(u.email) = :email')
            ->andWhere('a.isTest = :isTest')
            ->andWhere('a.status = :status')
            ->andWhere('a.paidAmount > 0')
            ->andWhere('a.paidAmount < a.totalAmount')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setParameter('isTest', $isTest)
            ->setParameter('status', ApplicationStatus::PartiallyPaid)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array{paid: int, refunded: int}
     */
    public function succeededPaymentTotals(Application $application): array
    {
        /** @var array{paid: mixed, refunded: mixed}|null $row */
        $row = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'COALESCE(SUM(p.amount - p.refundedAmount), 0) AS paid',
                'COALESCE(SUM(p.refundedAmount), 0) AS refunded',
            )
            ->from(Payment::class, 'p')
            ->andWhere('p.application = :application')
            ->andWhere('p.status = :status')
            ->setParameter('application', $application)
            ->setParameter('status', PaymentStatus::Succeeded)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'paid' => max(0, (int) ($row['paid'] ?? 0)),
            'refunded' => max(0, (int) ($row['refunded'] ?? 0)),
        ];
    }
}
