<?php

namespace App\Infrastructure\GoogleSheets\Dto;

readonly class ApplicationExportPayload
{
    public function __construct(
        public string $action,
        public string $applicationUuid,
        public string $name,
        public string $email,
        public string $phone,
        public string $productName,
        public string $participationOptionName,
        public string $pricingPeriodName,
        public string $totalAmount,
        public string $payNowAmount,
        public string $adultsCount,
        public string $childrenCount,
        public string $transferIncluded,
        public string $paymentFactor,
        public string $notes = '',
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'applicationUuid' => $this->applicationUuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'productName' => $this->productName,
            'participationOptionName' => $this->participationOptionName,
            'pricingPeriodName' => $this->pricingPeriodName,
            'totalAmount' => $this->totalAmount,
            'payNowAmount' => $this->payNowAmount,
            'adultsCount' => $this->adultsCount,
            'childrenCount' => $this->childrenCount,
            'transferIncluded' => $this->transferIncluded,
            'paymentFactor' => $this->paymentFactor,
            'notes' => $this->notes,
        ];
    }
}
