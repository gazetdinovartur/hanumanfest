<?php

namespace App\Service\Admin;

use App\Entity\Application;
use App\Enum\ApplicationStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Метрики дашборда — только MySQL (не Google Sheet).
 *
 * Семантика:
 * - unpaidApplications = заявки NEW (ещё без оплаты)
 * - paidApplications = заявки PAID
 * - receivedSum = SUM(paid_amount) по активным заявкам (не CANCELLED)
 * - registrationsTotal = все заявки кроме CANCELLED
 */
final class AdminDashboardStatsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{
     *     unpaidApplications: int,
     *     paidApplications: int,
     *     receivedSum: int,
     *     registrationsTotal: int
     * }
     */
    public function getRegistrationStats(): array
    {
        return [
            'unpaidApplications' => $this->countApplications(ApplicationStatus::New),
            'paidApplications' => $this->countApplications(ApplicationStatus::Paid),
            'receivedSum' => $this->sumReceivedOnApplications(),
            'registrationsTotal' => $this->countActiveRegistrations(),
        ];
    }

    private function countApplications(ApplicationStatus $status): int
    {
        return (int) $this->em->getRepository(Application::class)->count(['status' => $status]);
    }

    private function countActiveRegistrations(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Application::class, 'a')
            ->andWhere('a.status != :cancelled')
            ->setParameter('cancelled', ApplicationStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function sumReceivedOnApplications(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(a.paidAmount), 0)')
            ->from(Application::class, 'a')
            ->andWhere('a.status != :cancelled')
            ->setParameter('cancelled', ApplicationStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
