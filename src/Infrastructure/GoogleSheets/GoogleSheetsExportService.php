<?php

namespace App\Infrastructure\GoogleSheets;

use App\Entity\Application;
use App\Entity\Payment;
use App\Infrastructure\GoogleSheets\Dto\ApplicationExportPayload;
use App\Infrastructure\GoogleSheets\Dto\PaymentExportPayload;

class GoogleSheetsExportService
{
    public function __construct(
        private readonly GoogleSheetsClient $client,
    ) {
    }

    public function exportApplication(Application $application): void
    {
        $user = $application->getUser();
        if (!$user) {
            return;
        }

        $payload = $application->getPayload();
        $payNowAmount = (int) ($payload['payNowAmount'] ?? $application->getTotalAmount());

        $this->client->exportApplication(new ApplicationExportPayload(
            action: 'application',
            applicationUuid: (string) $application->getUuid(),
            name: $user->getName(),
            email: $user->getEmail(),
            phone: $user->getPhone() ?? '',
            productName: $application->getProduct()?->getName() ?? '',
            participationOptionName: (string) ($payload['participationOptionName'] ?? ''),
            pricingPeriodName: (string) ($payload['pricingPeriodName'] ?? ''),
            totalAmount: number_format($application->getTotalAmount(), 2, '.', ''),
            payNowAmount: number_format($payNowAmount, 2, '.', ''),
            adultsCount: (string) ($payload['adultsCount'] ?? 1),
            childrenCount: (string) ($payload['childrenCount'] ?? 0),
            transferIncluded: !empty($payload['transferIncluded']) ? '1' : '0',
            paymentFactor: (string) ($payload['paymentFactor'] ?? '1'),
            notes: trim((string) ($payload['tentRoommate'] ?? '')),
        ));
    }

    public function exportSuccessfulPayment(Payment $payment): void
    {
        $application = $payment->getApplication();
        $user = $application?->getUser();

        if (!$application || !$user) {
            return;
        }

        $totalAmount = $application->getTotalAmount();
        $paidTotal = $application->getPaidAmount();
        $remaining = max(0, $totalAmount - $paidTotal);

        $this->client->exportPayment(new PaymentExportPayload(
            email: $user->getEmail(),
            phone: $user->getPhone() ?? '',
            amount: number_format($payment->getAmount(), 2, '.', ''),
            currency: 'RUB',
            paymentId: $payment->getProviderPaymentId() ?? '',
            paidAt: ($payment->getPaidAt() ?? new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            applicationUuid: (string) $application->getUuid(),
            totalAmount: number_format($totalAmount, 2, '.', ''),
            paidTotal: number_format($paidTotal, 2, '.', ''),
            remaining: number_format($remaining, 2, '.', ''),
        ));
    }
}
