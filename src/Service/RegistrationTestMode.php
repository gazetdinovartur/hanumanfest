<?php

namespace App\Service;

use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\PricingPeriod;
use App\Entity\Product;
use App\Entity\SiteSettings;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RegistrationTestMode
{
    public const OPTION_CODE = 'TEST_REGISTRATION';
    public const OPTION_NAME = 'Тестовая регистрация';
    public const BASE_PRICE = 2;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function isEnabled(): bool
    {
        try {
            $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([], ['id' => 'ASC']);

            return $settings?->isRegistrationTestMode() ?? false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function setEnabled(bool $enabled): void
    {
        $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([], ['id' => 'ASC']);
        if ($settings === null) {
            $settings = new SiteSettings();
            $this->em->persist($settings);
        }

        $settings->setRegistrationTestMode($enabled);
        if ($enabled) {
            $this->ensureTestOption();
        }
        $this->em->flush();
    }

    public function isTestOption(?ParticipationOption $option): bool
    {
        return $option !== null && $option->getCode() === self::OPTION_CODE;
    }

    public function isTestOptionCode(?string $code): bool
    {
        return $code === self::OPTION_CODE;
    }

    /**
     * Создаёт/обновляет вариант «Тестовая регистрация» и цену 2 ₽ во всех периодах продукта.
     */
    public function ensureTestOption(?Product $product = null): ParticipationOption
    {
        $product ??= $this->productRepository->findActiveProduct();
        if (!$product) {
            throw new \RuntimeException('Активный продукт не найден — нельзя создать тестовый вариант.');
        }

        $option = $this->em->getRepository(ParticipationOption::class)->findOneBy([
            'product' => $product,
            'code' => self::OPTION_CODE,
        ]) ?? new ParticipationOption();

        $option->setProduct($product);
        $option->setCode(self::OPTION_CODE);
        $option->setName(self::OPTION_NAME);
        $this->em->persist($option);

        $periods = $this->em->getRepository(PricingPeriod::class)->findBy(['product' => $product]);
        foreach ($periods as $period) {
            $price = $this->em->getRepository(ParticipationPrice::class)->findOneBy([
                'pricingPeriod' => $period,
                'participationOption' => $option,
            ]) ?? new ParticipationPrice();
            $price->setPricingPeriod($period);
            $price->setParticipationOption($option);
            $price->setPrice(self::BASE_PRICE);
            $this->em->persist($price);
        }

        return $option;
    }

    /**
     * @param list<ParticipationOption> $options
     *
     * @return list<ParticipationOption>
     */
    public function filterForPublicApi(array $options): array
    {
        $enabled = $this->isEnabled();

        return array_values(array_filter(
            $options,
            fn (ParticipationOption $option): bool => $enabled || !$this->isTestOption($option),
        ));
    }

    /**
     * @param list<ParticipationOption> $options
     *
     * @return list<ParticipationOption>
     */
    public function filterForPricingMatrix(array $options): array
    {
        return $this->filterForPublicApi($options);
    }

    /**
     * @param list<ParticipationOption> $options
     *
     * @return list<ParticipationOption>
     */
    public function filterForSitePricing(array $options): array
    {
        return array_values(array_filter(
            $options,
            fn (ParticipationOption $option): bool => !$this->isTestOption($option),
        ));
    }
}
