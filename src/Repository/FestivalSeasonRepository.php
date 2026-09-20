<?php

namespace App\Repository;

use App\Entity\FestivalSeason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FestivalSeason>
 */
class FestivalSeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FestivalSeason::class);
    }

    public function findCurrent(): ?FestivalSeason
    {
        return $this->findOneBy(['isCurrent' => true], ['year' => 'DESC']);
    }

    public function findOneByYear(int $year): ?FestivalSeason
    {
        return $this->findOneBy(['year' => $year]);
    }

    /**
     * @return list<FestivalSeason>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['year' => 'DESC']);
    }

    public function nextYear(): int
    {
        $latest = $this->findOneBy([], ['year' => 'DESC']);

        return max(2027, (int) ($latest?->getYear() ?? 2026) + 1);
    }

    public function makeExclusiveCurrent(FestivalSeason $season): void
    {
        foreach ($this->findAll() as $other) {
            if ($other !== $season) {
                $other->setIsCurrent(false);
            }
        }

        $season->setIsCurrent(true);
    }
}
