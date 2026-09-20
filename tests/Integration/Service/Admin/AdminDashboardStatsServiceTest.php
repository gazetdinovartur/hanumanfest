<?php

namespace App\Tests\Integration\Service\Admin;

use App\Entity\Application;
use App\Entity\FestivalSeason;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Service\Admin\AdminDashboardStatsService;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\HanumanFestFixtures;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class AdminDashboardStatsServiceTest extends DatabaseTestCase
{
    public function testRegistrationStatsAreScopedToSeason(): void
    {
        $product = HanumanFestFixtures::seed($this->entityManager);
        $period = $this->entityManager->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);
        self::assertNotNull($period);
        $season2026 = $period->getSeason();
        self::assertNotNull($season2026);

        $season2027 = new FestivalSeason();
        $season2027->setYear(2027);
        $season2027->setName('Хануман Фест 2027');
        $season2027->setIsCurrent(true);
        $this->entityManager->persist($season2027);
        $season2026->setIsCurrent(false);

        $user = (new User())->setName('Test')->setEmail('a@test.ru')->setPhone('+79001112233');
        $this->entityManager->persist($user);

        $this->persistApplication($user, $product, $period, $season2026, ApplicationStatus::Paid, 5000, 5000, 'OWN_HOUSE_NO_FOOD', 'в своем жилье, без питания');
        $this->persistApplication($user, $product, $period, $season2026, ApplicationStatus::New, 3600, 0, 'OUR_TENT_FOOD', 'в нашей палатке, с питанием');
        $this->persistApplication($user, $product, $period, $season2027, ApplicationStatus::Paid, 8000, 8000, 'ONE_DAY', 'участие 1 день');

        $this->entityManager->flush();

        /** @var AdminDashboardStatsService $service */
        $service = static::getContainer()->get(AdminDashboardStatsService::class);

        $stats2026 = $service->getRegistrationStats($season2026);
        self::assertSame(2, $stats2026['registrationsTotal']);
        self::assertSame(1, $stats2026['paidApplications']);
        self::assertSame(0, $stats2026['refundsCount']);
        self::assertCount(2, $stats2026['byOption']);

        $stats2027 = $service->getRegistrationStats($season2027);
        self::assertSame(1, $stats2027['registrationsTotal']);
        self::assertSame(1, $stats2027['paidApplications']);
        self::assertSame([['label' => 'участие 1 день', 'count' => 1]], $stats2027['byOption']);
    }

    public function testRefundsCountApplicationsWithYookassaRefund(): void
    {
        $product = HanumanFestFixtures::seed($this->entityManager);
        $period = $this->entityManager->getRepository(\App\Entity\PricingPeriod::class)->findOneBy(['product' => $product]);
        self::assertNotNull($period);
        $season = $period->getSeason();
        self::assertNotNull($season);

        $user = (new User())->setName('Refund')->setEmail('refund@test.ru')->setPhone('+79001112234');
        $this->entityManager->persist($user);

        $application = $this->persistApplication($user, $product, $period, $season, ApplicationStatus::Refunded, 3600, 0, 'OWN_HOUSE_NO_FOOD', 'в своем жилье, без питания');
        $payment = new Payment();
        $payment->setApplication($application);
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId('yk-refund-001');
        $payment->setAmount(3600);
        $payment->setRefundedAmount(3600);
        $payment->setStatus(PaymentStatus::Succeeded);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        /** @var AdminDashboardStatsService $service */
        $service = static::getContainer()->get(AdminDashboardStatsService::class);
        $stats = $service->getRegistrationStats($season);

        self::assertSame(0, $stats['registrationsTotal']);
        self::assertSame(0, $stats['paidApplications']);
        self::assertSame(1, $stats['refundsCount']);
        self::assertSame([], $stats['byOption']);
    }

    private function persistApplication(
        User $user,
        \App\Entity\Product $product,
        \App\Entity\PricingPeriod $period,
        FestivalSeason $season,
        ApplicationStatus $status,
        int $total,
        int $paid,
        string $optionCode,
        string $optionName,
    ): Application {
        $application = (new Application())
            ->setUser($user)
            ->setProduct($product)
            ->setPricingPeriod($period)
            ->setSeason($season)
            ->setStatus($status)
            ->setTotalAmount($total)
            ->setPaidAmount($paid)
            ->setPayload([
                'participationOptionCode' => $optionCode,
                'participationOptionName' => $optionName,
            ]);
        $this->entityManager->persist($application);

        return $application;
    }
}
