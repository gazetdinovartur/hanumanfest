<?php

namespace App\Controller\Api;

use App\DTO\CreatePaymentRequest;
use App\Service\PaymentLinkService;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentLinkService $paymentLinkService,
        private readonly PaymentService $paymentService,
    ) {
    }

    #[Route('/payments', name: 'api_payments_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();

            $createRequest = new CreatePaymentRequest(
                email: $data['email'] ?? '',
                phone: $data['phone'] ?? '',
                amount: (int) ($data['amount'] ?? 0),
                manualAmount: isset($data['manual']) ? (int) $data['manual'] : null,
                applicationUuid: $data['applicationUuid'] ?? null,
            );

            return $this->json($this->paymentService->createYookassaPayment($createRequest));
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }

    #[Route('/payments/{id}/status', name: 'api_payments_status', methods: ['GET'])]
    public function status(string $id): JsonResponse
    {
        return $this->json($this->paymentService->getPaymentStatus($id));
    }

    #[Route('/payment-links/lookup', name: 'api_payment_links_lookup', methods: ['POST'])]
    public function lookup(Request $request): JsonResponse
    {
        $email = (string) ($request->toArray()['email'] ?? '');
        $result = $this->paymentLinkService->lookupPartialPayment($email, false)
            ?? $this->paymentLinkService->lookupPartialPayment($email, true);

        return $this->json($result ?? ['found' => false]);
    }

    #[Route('/payment-links/{token}', name: 'api_payment_links_show', methods: ['GET'])]
    public function showPaymentLink(string $token): JsonResponse
    {
        try {
            $paymentLink = $this->paymentLinkService->getValidLink($token);
            $application = $paymentLink->getApplication();
            $user = $application?->getUser();

            return $this->json([
                'token' => $paymentLink->getToken(),
                'application' => [
                    'uuid' => (string) $application?->getUuid(),
                    'name' => $user?->getName(),
                    'email' => $user?->getEmail(),
                    'totalAmount' => $application?->getTotalAmount(),
                    'paidAmount' => $application?->getPaidAmount(),
                    'remainingAmount' => $application?->getRemainingAmount() ?? 0,
                    'amountDueNow' => $application?->getAmountDueNow() ?? 0,
                    'status' => $application?->getStatus()->value,
                ],
            ]);
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }

    #[Route('/payment-links/{token}/pay', name: 'api_payment_links_pay', methods: ['POST'])]
    public function payFromLink(string $token): JsonResponse
    {
        try {
            return $this->json($this->paymentService->createPaymentFromLink($token));
        } catch (HttpExceptionInterface $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        }
    }
}
