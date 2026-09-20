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
        $payNowAmount = (int) ($payload['payNowAmount'] ?? $application->getTotalAmount());
        $payments = $this->succeededPayments($application, $extraPayment);
        $first = $payments[0] ?? null;
        $second = $payments[1] ?? null;

        return new RegistrationSheetRow(
            name: $user?->getName() ?? '',
            phone: $user?->getPhone() ?? '',
            email: $user?->getEmail() ?? '',
            adultsCount: (string) ($payload['adultsCount'] ?? 1),
            childrenCount: (string) ($payload['childrenCount'] ?? 0),
            totalAmount: $this->money($application->getTotalAmount()),
            payNowAmount: $this->money($payNowAmount),
            participationOptionName: (string) ($payload['participationOptionName'] ?? ''),
            transferIncluded: !empty($payload['transferIncluded']) ? '1' : '0',
            paymentFactor: (string) ($payload['paymentFactor'] ?? '1'),
            notes: trim((string) ($payload['tentRoommate'] ?? '')),
            payment1Amount: $first ? $this->money($first->getAmount()) : '',
            payment1Date: $this->paidAt($first),
            payment1Id: $first?->getProviderPaymentId() ?? '',
            payment2Amount: $second ? $this->money($second->getAmount()) : '',
            payment2Date: $this->paidAt($second),
            payment2Id: $second?->getProviderPaymentId() ?? '',
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
