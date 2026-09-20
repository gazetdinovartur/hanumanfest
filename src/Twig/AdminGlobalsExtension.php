<?php

namespace App\Twig;

use App\Service\Admin\AdminSeasonContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class AdminGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly AdminSeasonContext $seasonContext,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !str_starts_with($request->getPathInfo(), '/admin')) {
            return [
                'admin_seasons' => [],
                'admin_selected_season' => null,
            ];
        }

        return [
            'admin_seasons' => $this->seasonContext->listSeasons(),
            'admin_selected_season' => $this->seasonContext->getSelectedSeason(),
        ];
    }
}
