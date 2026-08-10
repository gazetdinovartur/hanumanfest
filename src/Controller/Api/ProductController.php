<?php

namespace App\Controller\Api;

use App\Entity\ParticipationOption;
use App\Entity\ParticipationPrice;
use App\Entity\PricingPeriod;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/product')]
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    #[Route('', name: 'api_product_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $product = $this->productRepository->findActiveProduct();
        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }

        $em = $this->productRepository->getEntityManager();
        $options = $em->getRepository(ParticipationOption::class)->findBy(
            ['product' => $product],
            ['name' => 'ASC'],
        );

        $periods = $em->getRepository(PricingPeriod::class)->findBy(
            ['product' => $product, 'isActive' => true],
            ['startAt' => 'ASC'],
        );

        $now = new \DateTimeImmutable();
        $activePeriod = null;
        foreach ($periods as $period) {
            if ($now >= $period->getStartAt() && $now <= $period->getEndAt()) {
                $activePeriod = $period;
                break;
            }
        }

        $prices = [];
        if ($activePeriod) {
            $priceRows = $em->getRepository(ParticipationPrice::class)->findBy([
                'pricingPeriod' => $activePeriod,
            ]);
            foreach ($priceRows as $row) {
                $prices[$row->getParticipationOption()?->getCode() ?? ''] = $row->getPrice();
            }
        }

        return $this->json([
            'name' => $product->getName(),
            'participationOptions' => array_map(static fn (ParticipationOption $o) => [
                'code' => $o->getCode(),
                'name' => $o->getName(),
                'price' => $prices[$o->getCode()] ?? null,
            ], $options),
            'activePricingPeriod' => $activePeriod ? [
                'name' => $activePeriod->getName(),
                'startAt' => $activePeriod->getStartAt()->format(\DateTimeInterface::ATOM),
                'endAt' => $activePeriod->getEndAt()->format(\DateTimeInterface::ATOM),
            ] : null,
        ]);
    }
}
