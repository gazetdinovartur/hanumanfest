<?php

namespace App\Service\Admin;

use App\Entity\FestivalSeason;
use App\Repository\FestivalSeasonRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminSeasonContext
{
    public const SESSION_KEY = 'admin_season_id';

    public function __construct(
        private readonly FestivalSeasonRepository $seasons,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getSelectedSeason(): ?FestivalSeason
    {
        try {
            $selectedId = $this->sessionSeasonId();
            if ($selectedId !== null) {
                $season = $this->seasons->find($selectedId);
                if ($season) {
                    return $season;
                }
            }

            return $this->seasons->findCurrent();
        } catch (\Throwable) {
            return null;
        }
    }

    public function setSelectedSeason(FestivalSeason $season): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $season->getId());
    }

    /**
     * @return list<FestivalSeason>
     */
    public function listSeasons(): array
    {
        try {
            return $this->seasons->findAllOrdered();
        } catch (\Throwable) {
            return [];
        }
    }

    private function sessionSeasonId(): ?int
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        $value = $request->getSession()->get(self::SESSION_KEY);
        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
