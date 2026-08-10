<?php

namespace App\Controller\Api;

use App\Repository\ProductRepository;
use App\Service\ScheduleQueryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/product')]
class ScheduleController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ScheduleQueryService $scheduleQueryService,
    ) {
    }

    #[Route('/schedule', name: 'api_product_schedule', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $product = $this->productRepository->findActiveProduct();
        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }

        $payload = $this->scheduleQueryService->getScheduleForProduct($product);

        $response = $this->json($payload);
        $response->setPublic();
        $response->setMaxAge(300);

        return $response;
    }
}
