<?php

namespace App\Command;

use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\PricingPeriod;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:hanuman-fest',
    description: 'Seed Hanuman Fest product, pricing periods and participation options',
)]
class SeedHanumanFestCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $repository = $this->entityManager->getRepository(Product::class);
        $product = $repository->findOneBy(['slug' => 'hanuman-fest'])
            ?? $repository->findOneBy(['slug' => 'hanuman-fest-2026']);

        if ($product) {
            $io->warning('Продукт уже существует, обновляем slug, периоды и цены.');
            $product->setSlug('hanuman-fest');
            $product->setName('Hanuman Fest');
            $product->setIsActive(true);
        } else {
            $product = new Product();
            $product->setName('Hanuman Fest');
            $product->setSlug('hanuman-fest');
            $product->setIsActive(true);
            $this->entityManager->persist($product);
        }

        $periods = [
            ['До 10 марта', '2026-01-01 00:00:00', '2026-03-10 23:59:59'],
            ['До 15 апреля', '2026-03-11 00:00:00', '2026-04-15 23:59:59'],
            ['До 1 июня', '2026-04-16 00:00:00', '2026-06-01 23:59:59'],
            ['После 1 июня', '2026-06-02 00:00:00', '2026-06-28 23:59:59'],
        ];

        $optionDefs = [
            ['OWN_HOUSE_NO_FOOD', 'в своем жилье (домик или палатка), без питания'],
            ['OWN_HOUSE_FOOD', 'в своем жилье с питанием'],
            ['OUR_TENT_NO_FOOD', 'в нашей палатке, без питания'],
            ['OUR_TENT_FOOD', 'в нашей палатке, с питанием'],
            ['ONE_DAY', 'участие 1 день без питания (без ночевой)'],
            ['ONE_DAY_FOOD', 'участие 1 день с питанием (без ночевой)'],
        ];

        $priceMatrix = [
            'До 10 марта' => [
                'OWN_HOUSE_NO_FOOD' => 3600,
                'OWN_HOUSE_FOOD' => 6400,
                'OUR_TENT_NO_FOOD' => 4600,
                'OUR_TENT_FOOD' => 7400,
                'ONE_DAY' => 2000,
                'ONE_DAY_FOOD' => 3400,
            ],
            'До 15 апреля' => [
                'OWN_HOUSE_NO_FOOD' => 4200,
                'OWN_HOUSE_FOOD' => 7000,
                'OUR_TENT_NO_FOOD' => 5200,
                'OUR_TENT_FOOD' => 8000,
                'ONE_DAY' => 2400,
                'ONE_DAY_FOOD' => 3800,
            ],
            'До 1 июня' => [
                'OWN_HOUSE_NO_FOOD' => 4800,
                'OWN_HOUSE_FOOD' => 7600,
                'OUR_TENT_NO_FOOD' => 5800,
                'OUR_TENT_FOOD' => 8600,
                'ONE_DAY' => 2800,
                'ONE_DAY_FOOD' => 4200,
            ],
            'После 1 июня' => [
                'OWN_HOUSE_NO_FOOD' => 5400,
                'OWN_HOUSE_FOOD' => 8200,
                'OUR_TENT_NO_FOOD' => 6400,
                'OUR_TENT_FOOD' => 9200,
                'ONE_DAY' => 3200,
                'ONE_DAY_FOOD' => 4600,
            ],
        ];

        $existingOptions = $this->entityManager->getRepository(ParticipationOption::class)->findBy(['product' => $product]);
        $options = [];
        foreach ($existingOptions as $existingOption) {
            $options[$existingOption->getCode()] = $existingOption;
        }

        foreach ($optionDefs as [$code, $name]) {
            $option = $options[$code] ?? new ParticipationOption();
            $option->setProduct($product);
            $option->setCode($code);
            $option->setName($name);
            $this->entityManager->persist($option);
            $options[$code] = $option;
        }

        $existingPeriods = $this->entityManager->getRepository(PricingPeriod::class)->findBy(['product' => $product]);
        $periodsByName = [];
        foreach ($existingPeriods as $existingPeriod) {
            $periodsByName[$existingPeriod->getName()] = $existingPeriod;
        }

        foreach ($periods as [$name, $start, $end]) {
            $period = $periodsByName[$name] ?? new PricingPeriod();
            $period->setProduct($product);
            $period->setName($name);
            $period->setStartAt(new \DateTimeImmutable($start));
            $period->setEndAt(new \DateTimeImmutable($end));
            $period->setIsActive(true);
            $this->entityManager->persist($period);

            foreach ($options as $code => $option) {
                $price = $this->entityManager->getRepository(ParticipationPrice::class)->findOneBy([
                    'pricingPeriod' => $period,
                    'participationOption' => $option,
                ]) ?? new ParticipationPrice();
                $price->setPricingPeriod($period);
                $price->setParticipationOption($option);
                $price->setPrice($priceMatrix[$name][$code]);
                $this->entityManager->persist($price);
            }
        }

        $this->entityManager->flush();

        $io->success('Hanuman Fest: периоды, варианты участия и цены обновлены.');

        return Command::SUCCESS;
    }
}
