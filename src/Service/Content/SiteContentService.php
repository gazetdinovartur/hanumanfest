<?php

namespace App\Service\Content;

use App\Entity\FaqItem;
use App\Entity\GalleryItem;
use App\Entity\HomeHero;
use App\Entity\HomeHighlight;
use App\Entity\InfoBlock;
use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\Person;
use App\Entity\PricingPeriod;
use App\Entity\Review;
use App\Entity\SiteSettings;
use App\Enum\HomeHighlightColumn;
use App\Enum\PersonKind;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final class SiteContentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function getHomeContext(): array
    {
        $hero = $this->em->getRepository(HomeHero::class)->findOneBy([], ['id' => 'ASC']);
        $settings = $this->em->getRepository(SiteSettings::class)->findOneBy([], ['id' => 'ASC']);

        return [
            'hero' => $hero,
            'settings' => $settings,
            'highlightsLeft' => $this->highlights(HomeHighlightColumn::Left),
            'highlightsRight' => $this->highlights(HomeHighlightColumn::Right),
            'guests' => $this->people(PersonKind::Guest),
            'musicians' => $this->people(PersonKind::Musician),
            'masters' => $this->people(PersonKind::Master),
            'gallery' => $this->em->getRepository(GalleryItem::class)->findBy(['published' => true], ['sortOrder' => 'ASC']),
            'faqs' => $this->em->getRepository(FaqItem::class)->findBy(['published' => true], ['sortOrder' => 'ASC']),
            'infoBlocks' => $this->em->getRepository(InfoBlock::class)->findBy(['published' => true], ['sortOrder' => 'ASC']),
            'reviews' => $this->em->getRepository(Review::class)->findBy(['published' => true], ['sortOrder' => 'ASC']),
            'pricingSections' => $this->pricingSections(),
        ];
    }

    /**
     * Accordion data for #cost: active pricing periods × options from the matrix.
     *
     * @return list<array{id: int, name: string, heading: string, isCurrent: bool, rows: list<array{price: int, label: string, emphasize: bool}>}>
     */
    private function pricingSections(): array
    {
        $product = $this->productRepository->findActiveProduct();
        if (!$product) {
            return [];
        }

        $periods = $this->em->getRepository(PricingPeriod::class)->findBy(
            ['product' => $product, 'isActive' => true],
            ['startAt' => 'ASC', 'endAt' => 'ASC'],
        );
        if ($periods === []) {
            return [];
        }

        $options = $this->sortOptionsByFestivalOrder(
            $this->em->getRepository(ParticipationOption::class)->findBy(['product' => $product])
        );
        if ($options === []) {
            return [];
        }

        $currentPeriod = $this->resolveCurrentPeriod($periods);
        $sections = [];

        foreach ($periods as $period) {
            $pid = $period->getId();
            if (null === $pid) {
                continue;
            }

            $priceByOptionId = [];
            $priceRows = $this->em->getRepository(ParticipationPrice::class)->findBy(['pricingPeriod' => $period]);
            foreach ($priceRows as $price) {
                $oid = $price->getParticipationOption()?->getId();
                if (null !== $oid) {
                    $priceByOptionId[$oid] = $price->getPrice();
                }
            }

            $rows = [];
            $dayBlockStarted = false;
            foreach ($options as $option) {
                $oid = $option->getId();
                if (null === $oid || !isset($priceByOptionId[$oid])) {
                    continue;
                }
                $isDayOption = str_starts_with($option->getCode(), 'ONE_DAY');
                $emphasize = $isDayOption && !$dayBlockStarted;
                if ($isDayOption) {
                    $dayBlockStarted = true;
                }
                $rows[] = [
                    'price' => $priceByOptionId[$oid],
                    'label' => $option->getName(),
                    'emphasize' => $emphasize,
                ];
            }

            if ($rows === []) {
                continue;
            }

            $sections[] = [
                'id' => $pid,
                'name' => $period->getName(),
                'heading' => $this->periodHeading($period->getName()),
                'isCurrent' => $currentPeriod?->getId() === $pid,
                'rows' => $rows,
            ];
        }

        if ($sections !== [] && !array_filter($sections, static fn (array $s): bool => $s['isCurrent'])) {
            $sections[array_key_last($sections)]['isCurrent'] = true;
        }

        return $sections;
    }

    /**
     * @param list<PricingPeriod> $periods
     */
    private function resolveCurrentPeriod(array $periods): ?PricingPeriod
    {
        $now = new \DateTimeImmutable();
        foreach ($periods as $period) {
            if ($now >= $period->getStartAt() && $now <= $period->getEndAt()) {
                return $period;
            }
        }
        if ($periods !== []) {
            $last = $periods[array_key_last($periods)];
            if ($now > $last->getEndAt()) {
                return $last;
            }
        }

        return null;
    }

    private function periodHeading(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return $trimmed;
        }
        if (preg_match('/^при\s+оплате/iu', $trimmed)) {
            return $trimmed;
        }
        if (preg_match('/^(до|после)\b/iu', $trimmed)) {
            return 'При оплате '.mb_strtolower(mb_substr($trimmed, 0, 1)).mb_substr($trimmed, 1);
        }

        return $trimmed;
    }

    /**
     * @param list<ParticipationOption> $options
     *
     * @return list<ParticipationOption>
     */
    private function sortOptionsByFestivalOrder(array $options): array
    {
        $orderMap = [
            'OWN_HOUSE_NO_FOOD' => 10,
            'OWN_HOUSE_FOOD' => 20,
            'OUR_TENT_NO_FOOD' => 30,
            'OUR_TENT_FOOD' => 40,
            'ONE_DAY' => 50,
            'ONE_DAY_FOOD' => 60,
        ];

        usort($options, static function (ParticipationOption $left, ParticipationOption $right) use ($orderMap): int {
            $leftOrder = $orderMap[$left->getCode()] ?? 999;
            $rightOrder = $orderMap[$right->getCode()] ?? 999;
            if ($leftOrder === $rightOrder) {
                return strcmp($left->getName(), $right->getName());
            }

            return $leftOrder <=> $rightOrder;
        });

        return $options;
    }

    /** @return list<Person> */
    private function people(PersonKind $kind): array
    {
        return $this->em->getRepository(Person::class)->findBy(
            ['kind' => $kind, 'published' => true],
            ['sortOrder' => 'ASC'],
        );
    }

    /** @return list<HomeHighlight> */
    private function highlights(HomeHighlightColumn $column): array
    {
        try {
            return $this->em->getRepository(HomeHighlight::class)->findBy(
                ['columnSide' => $column, 'published' => true],
                ['sortOrder' => 'ASC'],
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
