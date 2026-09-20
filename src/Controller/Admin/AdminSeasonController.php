<?php

namespace App\Controller\Admin;

use App\Repository\FestivalSeasonRepository;
use App\Service\Admin\AdminSeasonContext;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminSeasonController extends AbstractController
{
    public function __construct(
        private readonly AdminSeasonContext $seasonContext,
        private readonly FestivalSeasonRepository $seasons,
    ) {
    }

    #[AdminRoute(path: '/season', name: 'season_switch', options: ['methods' => ['POST']])]
    public function switchSeason(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_season', (string) $request->request->get('_season_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $season = $this->seasons->find((int) $request->request->get('season_id'));
        if ($season) {
            $this->seasonContext->setSelectedSeason($season);
        }

        $redirect = (string) ($request->request->get('redirect') ?: $request->headers->get('referer') ?: '/admin');
        $path = parse_url($redirect, PHP_URL_PATH) ?: '/admin';
        if (!str_starts_with($path, '/admin')) {
            return $this->redirectToRoute('admin');
        }

        $query = parse_url($redirect, PHP_URL_QUERY);

        return $this->redirect($query ? $path.'?'.$query : $path);
    }
}
