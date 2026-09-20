<?php

namespace App\Service;

use App\DTO\CreatePaymentRequest;
use App\Entity\Application;
use App\Entity\Payment;
use App\Entity\PaymentLink;
use App\Enum\ApplicationStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Infrastructure\GoogleSheets\GoogleSheetsExportService;
use App\Infrastructure\Yookassa\YookassaClient;
use App\Repository\ApplicationRepository;
use App\Repository\PaymentRepository;
use App\Util\PhoneNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRepository $paymentRepository,
        private readonly ApplicationRepository $applicationRepository,
        private readonly YookassaClient $yookassaClient,
        private readonly GoogleSheetsExportService $googleSheetsExportService,
        private readonly PaymentLinkService $paymentLinkService,
        private readonly PaymentNotificationService $paymentNotificationService,
        private readonly AfterResponseWork $afterResponseWork,
    ) {
    }

    /**
     * @return array{payment_id: string, gateway_url: string}
     */
    public function createYookassaPayment(CreatePaymentRequest $request): array
    {
        $application = null;

        if ($request->applicationUuid) {
            $application = $this->applicationRepository->findOneByUuid($request->applicationUuid);
            if (!$application) {
                throw new NotFoundHttpException('Application not found');
            }

            $email = $application->getUser()?->getEmail();
            $phone = $application->getUser()?->getPhone();
        } else {
            $email = $request->email;
            $phone = $request->phone;
        }

        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        $phone = PhoneNormalizer::toE164($phone);
        $amount = $this->resolvePaymentAmount($request, $application);

        if (!$email || !$phone || $amount <= 0) {
            throw new BadRequestHttpException('Invalid input');
        }

        $yookassaResult = $this->yookassaClient->createPayment($email, $phone, $amount, $this->paymentDescription($application));

        $payment = new Payment();
        $payment->setProvider(PaymentProvider::Yookassa);
        $payment->setProviderPaymentId($yookassaResult->paymentId);
        $payment->setAmount($amount);
        $payment->setStatus(PaymentStatus::Pending);

        if ($application) {
            $payment->setApplication($application);
        }

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return [
            'payment_id' => $yookassaResult->paymentId,
            'gateway_url' => $yookassaResult->gatewayUrl,
        ];
    }

  /**
   * @return array{payment_id: string, gateway_url: string}
   */
    public function createPaymentFromLink(string $token): array
    {
        $paymentLink = $this->paymentLinkService->getValidLink($token);
        $application = $paymentLink->getApplication();

        if (!$application) {
            throw new NotFoundHttpException('Application not found');
        }

        $remaining = $application->getRemainingAmount();
        if ($remaining <= 0) {
            throw new BadRequestHttpException('Application is already fully paid');
        }

        $user = $application->getUser();
        if (!$user) {
            throw new BadRequestHttpException('Application has no user');
        }

        return $this->createYookassaPayment(new CreatePaymentRequest(
            email: $user->getEmail(),
            phone: $user->getPhone() ?? '',
            amount: $remaining,
            applicationUuid: (string) $application->getUuid(),
        ));
    }

    /**
     * @param array<string, mixed> $webhookPayload
     */
    public function handleYookassaWebhook(array $webhookPayload): void
    {
        if (!isset($webhookPayload['object']) || !\is_array($webhookPayload['object'])) {
            throw new BadRequestHttpException('Bad request');
        }

        $paymentId = $this->resolveYookassaPaymentId($webhookPayload);
        if ($paymentId === '') {
            throw new BadRequestHttpException('Bad request');
        }

        $verified = $this->yookassaClient->verifyPayment($paymentId);
        $status = (string) ($verified['status'] ?? '');

        $payment = $this->paymentRepository->findOneByProviderPaymentId(
            PaymentProvider::Yookassa,
            $paymentId,
        );

        if (!$payment) {
            // Платёж создан старой системой (WordPress) — игнорируем, отвечаем ok.
            return;
        }

        if ($status === 'succeeded') {
            $this->markPaymentSucceeded($payment);
        }

        if ($status === 'canceled') {
            $payment->setStatus(PaymentStatus::Cancelled);
            $this->entityManager->flush();
        }

        $this->syncRefundedAmount($payment, $verified);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPaymentStatus(string $providerPaymentId): array
    {
        $payment = $this->paymentRepository->findOneByProviderPaymentId(
            PaymentProvider::Yookassa,
            $providerPaymentId,
        );

        if (!$payment) {
            return ['paid' => false];
        }

        if ($payment->getStatus() === PaymentStatus::Pending) {
            try {
                $verified = $this->yookassaClient->verifyPayment($providerPaymentId);
                if (\is_array($verified) && ($verified['status'] ?? '') === 'succeeded') {
                    $this->markPaymentSucceeded($payment);
                }
            } catch (\Throwable) {
                // Leave as pending; the return page can poll.
            }
        }

        $application = $payment->getApplication();
        $user = $application?->getUser();
        $payload = $application?->getPayload() ?? [];
        $payUrl = null;

        if ($application && $application->getStatus() === ApplicationStatus::PartiallyPaid) {
            $existingLink = $this->paymentLinkService->findForApplication($application);
            $paymentLink = $this->paymentLinkService->ensureForPartialApplication($application);
            if ($paymentLink instanceof PaymentLink) {
                $payUrl = $this->paymentLinkService->publicPayUrl($paymentLink);
                if (!$existingLink instanceof PaymentLink) {
                    $this->afterResponseWork->add(function () use ($application, $paymentLink): void {
                        $this->paymentNotificationService->sendPartialPaymentEmail($application, $paymentLink);
                    });
                }
            }
        }

        return [
            'paid' => $payment->getStatus() === PaymentStatus::Succeeded,
            'status' => strtolower($payment->getStatus()->value),
            'amount' => number_format($payment->getAmount(), 2, '.', ''),
            'email' => $user?->getEmail(),
            'phone' => $user?->getPhone(),
            'payment_id' => $payment->getProviderPaymentId(),
            'updated_at' => $payment->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'application_uuid' => $application ? (string) $application->getUuid() : null,
            'applicationStatus' => $application?->getStatus()->value,
            'paidAmount' => $application?->getPaidAmount(),
            'remainingAmount' => $application?->getRemainingAmount(),
            'totalAmount' => $application?->getTotalAmount(),
            'payUrl' => $payUrl,
            'name' => $user?->getName(),
            'participationOptionName' => $payload['participationOptionName'] ?? null,
            'adultsCount' => max(1, (int) ($payload['adultsCount'] ?? 1)),
            'childrenCount' => max(0, (int) ($payload['childrenCount'] ?? 0)),
            'transferIncluded' => !empty($payload['transferIncluded']),
            'tentRoommate' => trim((string) ($payload['tentRoommate'] ?? '')) ?: null,
            'pricingPeriodName' => $payload['pricingPeriodName'] ?? null,
            'paymentFactor' => isset($payload['paymentFactor']) ? (float) $payload['paymentFactor'] : null,
        ];
    }

    public function syncSucceededPaymentsToGoogleSheets(): int
    {
        $payments = $this->paymentRepository->findBy(['status' => PaymentStatus::Succeeded]);
        $count = 0;

        foreach ($payments as $payment) {
            $this->googleSheetsExportService->exportSuccessfulPayment($payment);
            ++$count;
        }

        return $count;
    }

    private function resolvePaymentAmount(CreatePaymentRequest $request, ?Application $application): int
    {
        if ($request->manualAmount !== null && $request->manualAmount > 0) {
            return $request->manualAmount;
        }

        if ($application) {
            return $application->getAmountDueNow();
        }

        return max(0, $request->amount);
    }

    private function paymentDescription(?Application $application): string
    {
        if ($application?->isTest()) {
            return 'Хануман Фест — тест регистрации';
        }

        return 'Оплата участия';
    }

    private function markPaymentSucceeded(Payment $payment): void
    {
        if ($payment->getStatus() === PaymentStatus::Succeeded) {
            return;
        }

        $payment->setStatus(PaymentStatus::Succeeded);
        $payment->setPaidAt(new \DateTimeImmutable());

        $application = $payment->getApplication();
        $createdPaymentLink = null;

        if ($application) {
            $this->refreshApplicationFromPayments($application);

            if ($application->getStatus() === ApplicationStatus::PartiallyPaid) {
                $existingLink = $this->paymentLinkService->findForApplication($application);
                $paymentLink = $this->paymentLinkService->ensureForPartialApplication($application);
                if ($paymentLink instanceof PaymentLink && !$existingLink instanceof PaymentLink) {
                    $createdPaymentLink = $paymentLink;
                }
            }
        }

        $this->entityManager->flush();

        if ($application) {
            $paymentId = $payment->getId();
            $applicationId = $application->getId();
            $linkId = $createdPaymentLink?->getId();
            $this->afterResponseWork->add(function () use ($paymentId, $applicationId, $linkId): void {
                $storedPayment = $paymentId !== null ? $this->paymentRepository->find($paymentId) : null;
                $storedApplication = $applicationId !== null ? $this->applicationRepository->find($applicationId) : null;
                if ($storedPayment instanceof Payment && $storedApplication instanceof Application) {
                    $this->googleSheetsExportService->exportSuccessfulPayment($storedPayment);
                }
                if ($linkId !== null && $storedApplication instanceof Application) {
                    $link = $this->entityManager->find(PaymentLink::class, $linkId);
                    if ($link instanceof PaymentLink) {
                        $this->paymentNotificationService->sendPartialPaymentEmail($storedApplication, $link);
                    }
                }
            });
        }
    }

    /**
     * @param array<string, mixed> $webhookPayload
     */
    private function resolveYookassaPaymentId(array $webhookPayload): string
    {
        $event = (string) ($webhookPayload['event'] ?? '');
        $object = $webhookPayload['object'];
        if (str_contains($event, 'refund')) {
            return trim((string) ($object['payment_id'] ?? ''));
        }

        return trim((string) ($object['id'] ?? ''));
    }

    /**
     * @param array<string, mixed> $verified
     */
    public function syncRefundedAmount(Payment $payment, array $verified): void
    {
        $refunded = $this->parseYookassaRub($verified['refunded_amount'] ?? 0);
        if ($refunded === $payment->getRefundedAmount()) {
            return;
        }

        $payment->setRefundedAmount($refunded);
        $application = $payment->getApplication();
        if ($application) {
            $this->refreshApplicationFromPayments($application);
        }

        $this->entityManager->flush();
    }

    public function syncRefundsFromYookassa(Payment $payment): bool
    {
        $providerPaymentId = $payment->getProviderPaymentId();
        if ($providerPaymentId === null || $providerPaymentId === '') {
            return false;
        }

        $verified = $this->yookassaClient->verifyPayment($providerPaymentId);
        $before = $payment->getRefundedAmount();
        $this->syncRefundedAmount($payment, $verified);

        return $payment->getRefundedAmount() !== $before;
    }

    private function refreshApplicationFromPayments(Application $application): void
    {
        $this->entityManager->flush();
        $totals = $this->applicationRepository->succeededPaymentTotals($application);
        ApplicationBalance::applyToApplication($application, $totals['paid'], $totals['refunded']);
    }

    private function parseYookassaRub(mixed $value): int
    {
        if (\is_array($value) && isset($value['value'])) {
            $value = $value['value'];
        }

        return (int) round((float) $value);
    }
}
