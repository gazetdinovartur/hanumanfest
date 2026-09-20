<?php

namespace App\Service\Admin;

use App\Entity\Application;
use App\Entity\FestivalSeason;
use App\Entity\Payment;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Метрики дашборда — только MySQL, только выбранный сезон.
 *
 * Семантика:
 * - registrationsTotal = все заявки кроме CANCELLED, REFUNDED и тестовых
 * - paidApplications = заявки PAID (без теста)
 * - refundsCount = заявки, по которым есть возврат в YooKassa (без теста)
 * - byOption = разбивка активных заявок по варианту участия
 */
final class AdminDashboardStatsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{
     *     registrationsTotal: int,
     *     paidApplications: int,
     *     refundsCount: int,
     *     byOption: list<array{label: string, count: int}>
     * }
     */
    public function getRegistrationStats(?FestivalSeason $season): array
    {
        if ($season === null) {
            return [
                'registrationsTotal' => 0,
                'paidApplications' => 0,
                'refundsCount' => 0,
                'byOption' => [],
            ];
        }

        return [
            'registrationsTotal' => $this->countActiveRegistrations($season),
            'paidApplications' => $this->countApplications($season, ApplicationStatus::Paid),
            'refundsCount' => $this->countRefunds($season),
            'byOption' => $this->countByOption($season),
        ];
    }

    private function countApplications(FestivalSeason $season, ApplicationStatus $status): int
    {
        return (int) $this->em->getRepository(Application::class)->count([
            'season' => $season,
            'status' => $status,
            'isTest' => false,
        ]);
    }

    private function countActiveRegistrations(FestivalSeason $season): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Application::class, 'a')
            ->andWhere('a.season = :season')
            ->andWhere('a.isTest = false')
            ->andWhere('a.status NOT IN (:inactive)')
            ->setParameter('season', $season)
            ->setParameter('inactive', [ApplicationStatus::Cancelled, ApplicationStatus::Refunded])
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countRefunds(FestivalSeason $season): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT a.id)')
            ->from(Payment::class, 'p')
            ->innerJoin('p.application', 'a')
            ->andWhere('a.season = :season')
            ->andWhere('a.isTest = false')
            ->andWhere('p.status = :succeeded')
            ->andWhere('p.refundedAmount > 0')
            ->setParameter('season', $season)
            ->setParameter('succeeded', PaymentStatus::Succeeded)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function countByOption(FestivalSeason $season): array
    {
        /** @var list<Application> $applications */
        $applications = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Application::class, 'a')
            ->andWhere('a.season = :season')
            ->andWhere('a.isTest = false')
            ->andWhere('a.status NOT IN (:inactive)')
            ->setParameter('season', $season)
            ->setParameter('inactive', [ApplicationStatus::Cancelled, ApplicationStatus::Refunded])
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($applications as $application) {
            $payload = $application->getPayload();
            $code = trim((string) ($payload['participationOptionCode'] ?? ''));
            $label = trim((string) ($payload['participationOptionName'] ?? ''));
            if ($label === '') {
                $label = $code !== '' ? $code : 'Не указан';
            }
            $key = $code !== '' ? $code : $label;
            if (!isset($counts[$key])) {
                $counts[$key] = ['label' => $label, 'count' => 0];
            }
            ++$counts[$key]['count'];
        }

        usort($counts, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));

        return array_values($counts);
    }
}
