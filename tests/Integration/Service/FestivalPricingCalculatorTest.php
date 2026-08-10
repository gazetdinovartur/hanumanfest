<?php

namespace App\Tests\Integration\Service;

use App\DTO\CalculatePriceRequest;
use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Service\FestivalPricingCalculator;
use App\Service\PaymentService;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\HanumanFestFixtures;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class FestivalPricingCalculatorTest extends DatabaseTestCase
{
    public function testCalculatesLegacyFormulaForSingleAdultWithHalfPayment(): void
    {
        $product = HanumanFestFixtures::seed($this->entityManager);
        $optionId = $this->entityManager->getRepository(\App\Entity\ParticipationOption::class)
            ->findOneBy(['product' => $product])
            ?->getId();

        /** @var FestivalPricingCalculator $calculator */
        $calculator = static::getContainer()->get(FestivalPricingCalculator::class);

        $result = $calculator->calculate(new CalculatePriceRequest(
            participationOptionId: (int) $optionId,
            registrationDate: new \DateTimeImmutable('2026-02-01'),
            adultsCount: 1,
            childrenCount: 0,
            transferIncluded: false,
            paymentFactor: 0.5,
        ));

        self::assertSame(3600, $result->totalAmount);
        self::assertSame(1800, $result->payNowAmount);
        self::assertSame('До 10 марта', $result->pricingPeriodName);
    }

    public function testCalculatesGroupDiscountAndTransfer(): void
    {
        $product = HanumanFestFixtures::seed($this->entityManager);
        $optionId = $this->entityManager->getRepository(\App\Entity\ParticipationOption::class)
            ->findOneBy(['product' => $product])
            ?->getId();

        /** @var FestivalPricingCalculator $calculator */
        $calculator = static::getContainer()->get(FestivalPricingCalculator::class);

        $result = $calculator->calculate(new CalculatePriceRequest(
            participationOptionId: (int) $optionId,
            registrationDate: new \DateTimeImmutable('2026-02-01'),
            adultsCount: 2,
            childrenCount: 1,
            transferIncluded: true,
            paymentFactor: 1.0,
        ));

        // 3600*2*0.98 + 600*2 + 3600*1*0.5 + 600*1 = 7056 + 1200 + 1800 + 600 = 10656
        self::assertSame(10656, $result->totalAmount);
        self::assertSame(144, $result->discountAmount);
    }
}
