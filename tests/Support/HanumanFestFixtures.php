<?php

namespace App\Tests\Support;

use App\Entity\FestivalSeason;
use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\PricingPeriod;
use App\Entity\Product;
use App\Entity\SiteSettings;
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

    public static function enableRegistrationTestMode(EntityManagerInterface $entityManager): void
    {
        $settings = $entityManager->getRepository(SiteSettings::class)->findOneBy([]) ?? new SiteSettings();
        $settings->setRegistrationTestMode(true);
        $entityManager->persist($settings);
        $entityManager->flush();
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
