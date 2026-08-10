<?php

namespace App\Controller\Api;

use App\DTO\CalculatePriceRequest;
use App\DTO\CreateApplicationRequest;
use App\Service\ApplicationService;
use App\Service\FestivalPricingCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApplicationController extends AbstractController
{
    public function __construct(
        private readonly FestivalPricingCalculator $pricingCalculator,
        private readonly ApplicationService $applicationService,
    ) {
    }

    #[Route('/calculate', name: 'api_calculate', methods: ['POST'])]
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $calculateRequest = new CalculatePriceRequest(
            participationOptionCode: $data['participationOptionCode'] ?? '',
            adultsCount: max(1, (int) ($data['adultsCount'] ?? 1)),
            childrenCount: max(0, (int) ($data['childrenCount'] ?? 0)),
            transferIncluded: (bool) ($data['transferIncluded'] ?? false),
            paymentFactor: (float) ($data['paymentFactor'] ?? 1.0),
        );

        $result = $this->pricingCalculator->calculate($calculateRequest);

        return $this->json([
            'totalAmount' => $result->totalAmount,
            'discountAmount' => $result->discountAmount,
            'payNowAmount' => $result->payNowAmount,
            'pricingPeriodName' => $result->pricingPeriodName,
            'participationOptionName' => $result->participationOptionName,
        ]);
    }

    #[Route('/applications', name: 'api_applications_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
            $createRequest = new CreateApplicationRequest(
                name: $data['name'] ?? '',
                email: $data['email'] ?? '',
                phone: $data['phone'] ?? null,
                participationOptionCode: $data['participationOptionCode'] ?? '',
                payload: $data['payload'] ?? [],
                adultsCount: max(1, (int) ($data['adultsCount'] ?? 1)),
                childrenCount: max(0, (int) ($data['childrenCount'] ?? 0)),
                transferIncluded: (bool) ($data['transferIncluded'] ?? false),
                paymentFactor: (float) ($data['paymentFactor'] ?? 1.0),
                payNowAmount: isset($data['payNowAmount']) ? (int) $data['payNowAmount'] : null,
            );

            $application = $this->applicationService->create($createRequest);
            $payload = $application->getPayload();

            return $this->json([
                'uuid' => (string) $application->getUuid(),
                'status' => $application->getStatus()->value,
                'totalAmount' => $application->getTotalAmount(),
                'payNowAmount' => (int) ($payload['payNowAmount'] ?? $application->getTotalAmount()),
                'pricingPeriodName' => $payload['pricingPeriodName'] ?? null,
                'participationOptionName' => $payload['participationOptionName'] ?? null,
            ], Response::HTTP_CREATED);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }
}
