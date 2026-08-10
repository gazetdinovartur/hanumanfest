<?php

namespace App\DTO;

class CalculatePriceRequest
{
    public function __construct(
        public readonly string $participationOptionCode,
        public readonly ?\DateTimeImmutable $registrationDate = null,
        public readonly int $adultsCount = 1,
        public readonly int $childrenCount = 0,
        public readonly bool $transferIncluded = false,
        public readonly float $paymentFactor = 1.0,
    ) {
    }
}
