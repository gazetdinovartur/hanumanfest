<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ScheduleEvent;
use App\Entity\ScheduleImport;
use App\Entity\ScheduleVenue;
use App\Enum\ScheduleEventType;
use Doctrine\ORM\EntityManagerInterface;

final class ScheduleQueryService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     product: array{name: string},
     *     importedAt: string|null,
     *     days: list<array{
     *         date: string,
     *         label: string,
     *         venues: list<array{
     *             slug: string,
     *             name: string,
     *             events: list<array{
     *                 id: int,
     *                 startsAt: string,
     *                 endsAt: string,
     *                 title: string,
     *                 type: string
     *             }>
     *         }>
     *     }>
     * }
     */
    public function getScheduleForProduct(Product $product, bool $includeMeals = true): array
    {
        $latestImport = $this->entityManager->getRepository(ScheduleImport::class)->findOneBy(
            ['product' => $product],
            ['importedAt' => 'DESC'],
        );

        $venues = $this->entityManager->getRepository(ScheduleVenue::class)->findBy(
            ['product' => $product],
            ['sortOrder' => 'ASC'],
        );

        $qb = $this->entityManager->createQueryBuilder()
            ->select('e', 'v')
            ->from(ScheduleEvent::class, 'e')
            ->join('e.venue', 'v')
            ->where('e.product = :product')
            ->andWhere('e.isPublished = true')
            ->setParameter('product', $product)
            ->orderBy('e.startsAt', 'ASC');

        if (!$includeMeals) {
            $qb->andWhere('e.eventType != :meal')
                ->setParameter('meal', ScheduleEventType::Meal);
        }

        /** @var list<ScheduleEvent> $events */
        $events = $qb->getQuery()->getResult();

        /** @var array<string, array{date: string, label: string, venues: array<string, array{slug: string, name: string, events: list<array<string, mixed>>}>}> $days */
        $days = [];

        foreach ($events as $event) {
            $startsAt = $event->getStartsAt();
            $dateKey = $startsAt->format('Y-m-d');
            $venue = $event->getVenue();

            if (!isset($days[$dateKey])) {
                $days[$dateKey] = [
                    'date' => $dateKey,
                    'label' => $this->dayLabel($startsAt),
                    'venues' => [],
                ];
            }

            $venueSlug = $venue->getSlug();
            if (!isset($days[$dateKey]['venues'][$venueSlug])) {
                $days[$dateKey]['venues'][$venueSlug] = [
                    'slug' => $venueSlug,
                    'name' => $venue->getName(),
                    'events' => [],
                ];
            }

            $days[$dateKey]['venues'][$venueSlug]['events'][] = [
                'id' => $event->getId(),
                'startsAt' => $startsAt->format(\DateTimeInterface::ATOM),
                'endsAt' => $event->getEndsAt()->format(\DateTimeInterface::ATOM),
                'title' => $event->getTitle(),
                'type' => $event->getEventType()->value,
            ];
        }

        // Ensure empty venues appear on days where they have no events is NOT needed;
        // include venue order from master list for days that have any events
        $normalizedDays = [];
        foreach ($days as $day) {
            $orderedVenues = [];
            foreach ($venues as $venue) {
                $slug = $venue->getSlug();
                if (isset($day['venues'][$slug])) {
                    $orderedVenues[] = $day['venues'][$slug];
                }
            }
            $day['venues'] = $orderedVenues;
            $normalizedDays[] = $day;
        }

        usort($normalizedDays, static fn (array $a, array $b) => $a['date'] <=> $b['date']);

        return [
            'product' => [
                'name' => $product->getName(),
            ],
            'importedAt' => $latestImport?->getImportedAt()->format(\DateTimeInterface::ATOM),
            'days' => $normalizedDays,
        ];
    }

    private function dayLabel(\DateTimeImmutable $date): string
    {
        $labels = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье',
        ];

        return ($labels[(int) $date->format('N')] ?? $date->format('l')).', '.$date->format('d.m.Y');
    }
}
