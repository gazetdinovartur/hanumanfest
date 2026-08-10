<?php

namespace App\Service;

use App\DTO\CalculatePriceRequest;
use App\DTO\PricingContext;
use App\DTO\PricingResult;
use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\PricingPeriod;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FestivalPricingCalculator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function calculate(CalculatePriceRequest $request): PricingResult
    {
        return $this->calculateWithContext($request)->result;
    }

    public function calculateWithContext(CalculatePriceRequest $request): PricingContext
    {
        $product = $this->productRepository->findActiveProduct();

        if (!$product) {
            throw new NotFoundHttpException('Active product not found.');
        }

        $participationOption = $this->entityManager->find(ParticipationOption::class, $request->participationOptionId);

        if (!$participationOption || $participationOption->getProduct()?->getId() !== $product->getId()) {
            throw new NotFoundHttpException('Выбранный вариант участия не найден.');
        }

        $registrationDate = $request->registrationDate ?? new \DateTimeImmutable();
        $pricingPeriod = $this->resolvePricingPeriod($product, $registrationDate);
        $participationPrice = $this->resolveParticipationPrice($pricingPeriod, $participationOption);

        $basePrice = $participationPrice->getPrice();
        $adultsCount = max(1, $request->adultsCount);
        $childrenCount = max(0, $request->childrenCount);
        $transferPrice = $request->transferIncluded ? 600 : 0;

        // Legacy formula parity (Forminator calculation-1):
        // (select-1 * number-1 * (1 - ((number-1 * max(number-1-1,0) / max(number-1-1,1))/100)))
        // + (checkbox-3 * number-1)
        // + (select-1 * number-3 * 0.5)
        // + (checkbox-3 * number-3)
        $adultsDiscountMultiplier = $this->calculateAdultsDiscountMultiplier($adultsCount);
        $totalBeforePaymentFactor =
            ($basePrice * $adultsCount * $adultsDiscountMultiplier)
            + ($transferPrice * $adultsCount)
            + ($basePrice * $childrenCount * 0.5)
            + ($transferPrice * $childrenCount);

        $paymentFactor = max(0.0, min(1.0, $request->paymentFactor));

        $totalAmount = (int) round($totalBeforePaymentFactor);
        $payNowAmount = (int) round($totalBeforePaymentFactor * $paymentFactor);
        $discountAmount = (int) round(($basePrice * $adultsCount) - ($basePrice * $adultsCount * $adultsDiscountMultiplier));

        $result = new PricingResult(
            totalAmount: $totalAmount,
            discountAmount: $discountAmount,
            payNowAmount: $payNowAmount,
            pricingPeriodName: $pricingPeriod->getName(),
            participationOptionName: $participationOption->getName(),
        );

        return new PricingContext($result, $product, $pricingPeriod, $participationOption);
    }

    private function calculateAdultsDiscountMultiplier(int $adultsCount): float
    {
        $numerator = $adultsCount * max($adultsCount - 1, 0);
        $denominator = max($adultsCount - 1, 1);
        $discountPercent = $numerator / $denominator;

        return 1 - ($discountPercent / 100);
    }

    private function resolvePricingPeriod(Product $product, \DateTimeImmutable $date): PricingPeriod
    {
        $periods = $this->entityManager->getRepository(PricingPeriod::class)->findBy(
            ['product' => $product, 'isActive' => true],
            ['startAt' => 'ASC'],
        );

        foreach ($periods as $period) {
            if ($date >= $period->getStartAt() && $date <= $period->getEndAt()) {
                return $period;
            }
        }

        // Outside seeded windows: keep the latest (late) price until the next season is configured.
        if ($periods !== []) {
            $last = $periods[array_key_last($periods)];
            if ($date > $last->getEndAt()) {
                return $last;
            }
        }

        throw new NotFoundHttpException('No active pricing period found for the given date.');
    }

    private function resolveParticipationPrice(PricingPeriod $pricingPeriod, ParticipationOption $participationOption): ParticipationPrice
    {
        $price = $this->entityManager->getRepository(ParticipationPrice::class)->findOneBy([
            'pricingPeriod' => $pricingPeriod,
            'participationOption' => $participationOption,
        ]);

        if (!$price) {
            throw new NotFoundHttpException('Price not configured for the selected period and participation option.');
        }

        return $price;
    }
}
