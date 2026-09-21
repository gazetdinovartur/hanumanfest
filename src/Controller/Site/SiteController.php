<?php

namespace App\Controller\Site;

use App\Repository\SitePageRepository;
use App\Service\Content\SiteContentService;
use App\Service\PaymentLinkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SiteController extends AbstractController
{
    public function __construct(
        private readonly SiteContentService $siteContentService,
        private readonly SitePageRepository $sitePageRepository,
        private readonly PaymentLinkService $paymentLinkService,
    ) {
    }

    #[Route('/', name: 'site_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('site/home.html.twig', $this->siteContentService->getHomeContext());
    }

    #[Route('/отзывы', name: 'site_reviews', methods: ['GET'])]
    public function reviews(): Response
    {
        return $this->render('site/reviews.html.twig', $this->siteContentService->getReviewsPageContext());
    }

    #[Route('/галерея', name: 'site_gallery', methods: ['GET'])]
    public function gallery(): Response
    {
        return $this->render('site/gallery.html.twig', $this->siteContentService->getGalleryPageContext());
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
        $paymentLink = $this->paymentLinkService->findByToken($token);
        $application = $paymentLink?->getApplication();
        $user = $application?->getUser();
        $payload = $application?->getPayload() ?? [];

        return $this->render('site/pay.html.twig', [
            'token' => $token,
            'state' => $this->paymentLinkService->state($paymentLink),
            'name' => $user?->getName(),
            'paidAmount' => $application?->getPaidAmount() ?? 0,
            'remainingAmount' => $application?->getRemainingAmount() ?? 0,
            'amountDueNow' => $application?->getAmountDueNow() ?? 0,
            'totalAmount' => $application?->getTotalAmount() ?? 0,
            'optionName' => $payload['participationOptionName'] ?? null,
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
