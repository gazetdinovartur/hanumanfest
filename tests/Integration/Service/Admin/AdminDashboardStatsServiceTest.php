<?php

namespace App\Tests\Integration\Service\Admin;

use App\Entity\Application;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Service\Admin\AdminDashboardStatsService;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\HanumanFestFixtures;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class AdminDashboardStatsServiceTest extends DatabaseTestCase
{
    public function testRegistrationStatsFromDatabase(): void
    {
        $product = HanumanFestFixtures::seed($this->entityManager);
        $period = $this->entityManager->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);
        self::assertNotNull($period);

        $user = (new User())->setName('Test')->setEmail('a@test.ru')->setPhone('+79001112233');
        $this->entityManager->persist($user);

        $cases = [
            [ApplicationStatus::New, 5000, 0],
            [ApplicationStatus::Paid, 5000, 5000],
            [ApplicationStatus::Paid, 8000, 8000],
            [ApplicationStatus::PartiallyPaid, 10000, 4000],
            [ApplicationStatus::Cancelled, 3000, 1000],
        ];

        foreach ($cases as [$status, $total, $paid]) {
            $app = (new Application())
                ->setUser($user)
                ->setProduct($product)
                ->setPricingPeriod($period)
                ->setStatus($status)
                ->setTotalAmount($total)
                ->setPaidAmount($paid);
            $this->entityManager->persist($app);
        }

        $this->entityManager->flush();

        /** @var AdminDashboardStatsService $service */
        $service = static::getContainer()->get(AdminDashboardStatsService::class);
        $stats = $service->getRegistrationStats();

        self::assertSame(1, $stats['unpaidApplications']);
        self::assertSame(2, $stats['paidApplications']);
        // NEW 0 + PAID 5000 + PAID 8000 + PARTIAL 4000; CANCELLED ignored
        self::assertSame(17000, $stats['receivedSum']);
        self::assertSame(4, $stats['registrationsTotal']);
    }
}
