<?php

namespace App\Tests\Support;

use App\Entity\FestivalSeason;
use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\PricingPeriod;
use App\Entity\Product;
use App\Entity\SiteSettings;
use App\Service\RegistrationTestMode;
use Doctrine\ORM\EntityManagerInterface;

final class HanumanFestFixtures
{
    public static function seed(EntityManagerInterface $entityManager): Product
    {
        $product = new Product();
        $product->setName('Hanuman Fest');
        $product->setSlug('hanuman-fest');
        $product->setIsActive(true);
        $entityManager->persist($product);

        $season = new FestivalSeason();
        $season->setYear(2026);
        $season->setName('Хануман Фест 2026');
        $season->setIsCurrent(true);
        $entityManager->persist($season);

        $period = new PricingPeriod();
        $period->setProduct($product);
        $period->setSeason($season);
        $period->setName('До 10 марта');
        $period->setStartAt(new \DateTimeImmutable('2026-01-01 00:00:00'));
        $period->setEndAt(new \DateTimeImmutable('2026-12-31 23:59:59'));
        $period->setIsActive(true);
        $entityManager->persist($period);

        $option = new ParticipationOption();
        $option->setProduct($product);
        $option->setCode('OWN_HOUSE_NO_FOOD');
        $option->setName('в своем жилье (домик или палатка), без питания');
        $entityManager->persist($option);

        $price = new ParticipationPrice();
        $price->setPricingPeriod($period);
        $price->setParticipationOption($option);
        $price->setPrice(3600);
        $entityManager->persist($price);

        $entityManager->flush();

        return $product;
    }

    public static function enableRegistrationTestMode(EntityManagerInterface $entityManager): ParticipationOption
    {
        $settings = $entityManager->getRepository(SiteSettings::class)->findOneBy([]) ?? new SiteSettings();
        $settings->setRegistrationTestMode(true);
        $entityManager->persist($settings);

        $product = $entityManager->getRepository(Product::class)->findOneBy(['slug' => 'hanuman-fest']);
        if (!$product) {
            throw new \RuntimeException('Product hanuman-fest missing — seed first.');
        }

        $option = $entityManager->getRepository(ParticipationOption::class)->findOneBy([
            'product' => $product,
            'code' => RegistrationTestMode::OPTION_CODE,
        ]) ?? new ParticipationOption();
        $option->setProduct($product);
        $option->setCode(RegistrationTestMode::OPTION_CODE);
        $option->setName(RegistrationTestMode::OPTION_NAME);
        $entityManager->persist($option);

        foreach ($entityManager->getRepository(PricingPeriod::class)->findBy(['product' => $product]) as $period) {
            $price = $entityManager->getRepository(ParticipationPrice::class)->findOneBy([
                'pricingPeriod' => $period,
                'participationOption' => $option,
            ]) ?? new ParticipationPrice();
            $price->setPricingPeriod($period);
            $price->setParticipationOption($option);
            $price->setPrice(RegistrationTestMode::BASE_PRICE);
            $entityManager->persist($price);
        }

        $entityManager->flush();

        return $option;
    }

    public static function currentSeason(EntityManagerInterface $entityManager): FestivalSeason
    {
        $season = $entityManager->getRepository(FestivalSeason::class)->findOneBy(['isCurrent' => true]);
        if (!$season) {
            throw new \RuntimeException('No current festival season in fixtures.');
        }

        return $season;
    }
}
