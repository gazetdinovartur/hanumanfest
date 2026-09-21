<?php

namespace App\Infrastructure\GoogleSheets;

use App\Entity\Application;
use App\Entity\Payment;
use App\Enum\PaymentStatus;
use App\Infrastructure\GoogleSheets\Dto\RegistrationSheetRow;

class GoogleSheetsExportService
{
    public function __construct(
        private readonly GoogleSheetsClient $client,
    ) {
    }

    public function exportApplication(Application $application): void
    {
        if (!$application->getUser()) {
            return;
        }

        $this->client->exportApplication($this->rowFrom($application), $application->isTest());
    }

    public function exportSuccessfulPayment(Payment $payment): void
    {
        $application = $payment->getApplication();
        if (!$application) {
            return;
        }

        $this->client->exportPayment($this->rowFrom($application, $payment), $application->isTest());
    }

    private function rowFrom(Application $application, ?Payment $extraPayment = null): RegistrationSheetRow
    {
        $user = $application->getUser();
        $payload = $application->getPayload();
        $payments = $this->succeededPayments($application, $extraPayment);

        return new RegistrationSheetRow(
            name: $user?->getName() ?? '',
            phone: $user?->getPhone() ?? '',
            email: $user?->getEmail() ?? '',
            adultsCount: (string) ($payload['adultsCount'] ?? 1),
            childrenCount: (string) ($payload['childrenCount'] ?? 0),
            totalAmount: $this->money($application->getTotalAmount()),
            participationOptionName: (string) ($payload['participationOptionName'] ?? ''),
            transferIncluded: !empty($payload['transferIncluded']) ? 'да' : 'нет',
            notes: trim((string) ($payload['tentRoommate'] ?? '')),
            payments: $this->paymentsText($payments),
            paidTotal: $this->money($application->getPaidAmount()),
            remaining: $this->money($application->getRemainingAmount()),
            pricingPeriodName: (string) ($payload['pricingPeriodName'] ?? ''),
            applicationUuid: (string) $application->getUuid(),
        );
    }

    /**
     * @return list<Payment>
     */
    private function succeededPayments(Application $application, ?Payment $extraPayment): array
    {
        $payments = [];
        foreach ($application->getPayments() as $payment) {
            if ($payment->getStatus() === PaymentStatus::Succeeded) {
                $payments[] = $payment;
            }
        }
        if (
            $extraPayment
            && $extraPayment->getStatus() === PaymentStatus::Succeeded
            && !\in_array($extraPayment, $payments, true)
        ) {
            $payments[] = $extraPayment;
        }
        usort($payments, static function (Payment $a, Payment $b): int {
            $left = $a->getPaidAt()?->getTimestamp() ?? 0;
            $right = $b->getPaidAt()?->getTimestamp() ?? 0;
            if ($left !== $right) {
                return $left <=> $right;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        return array_values($payments);
    }

    /**
     * @param list<Payment> $payments
     */
    private function paymentsText(array $payments): string
    {
        $lines = [];
        foreach ($payments as $payment) {
            $parts = [];
            $parts[] = $this->money($payment->getAmount()).' ₽';
            $paidAt = $this->paidAt($payment);
            if ($paidAt !== '') {
                $parts[] = $paidAt;
            }
            $id = trim((string) $payment->getProviderPaymentId());
            if ($id !== '') {
                $parts[] = $id;
            }
            $lines[] = implode(' · ', $parts);
        }

        return implode("\n", $lines);
    }

    private function money(int $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function paidAt(?Payment $payment): string
    {
        $at = $payment?->getPaidAt();
        if (!$at) {
            return '';
        }

        return $at->setTimezone(new \DateTimeZone('Europe/Moscow'))->format('d.m.Y H:i:s');
    }
}
