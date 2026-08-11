<?php

namespace App\Controller\Site;

use App\Repository\SitePageRepository;
use App\Service\Content\SiteContentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SiteController extends AbstractController
{
    public function __construct(
        private readonly SiteContentService $siteContentService,
        private readonly SitePageRepository $sitePageRepository,
    ) {
    }

    #[Route('/', name: 'site_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('site/home.html.twig', $this->siteContentService->getHomeContext());
    }

    #[Route('/program', name: 'site_program', methods: ['GET'])]
    public function program(): Response
    {
        return $this->render('site/program.html.twig');
    }

    #[Route('/registration', name: 'site_registration', methods: ['GET'])]
    public function registration(): Response
    {
        return $this->render('site/registration.html.twig');
    }

    #[Route('/return', name: 'site_return', methods: ['GET'])]
    public function paymentReturn(): Response
    {
        return $this->render('site/return.html.twig');
    }

    #[Route('/pay/{token}', name: 'site_pay', methods: ['GET'], requirements: ['token' => '[A-Za-z0-9_-]+'])]
    public function pay(string $token): Response
    {
        return $this->render('site/pay.html.twig', [
            'token' => $token,
        ]);
    }

    #[Route('/{slug}', name: 'site_page', methods: ['GET'], requirements: ['slug' => '[^/]+'], priority: -100)]
    public function page(string $slug): Response
    {
        $page = $this->sitePageRepository->findPublishedBySlug(rawurldecode($slug));
        if (!$page) {
            throw new NotFoundHttpException('Страница не найдена');
        }

        return $this->render('site/page.html.twig', [
            'page' => $page,
        ]);
    }
}
