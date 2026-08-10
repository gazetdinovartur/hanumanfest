<?php

namespace App\DTO;

readonly class CreateApplicationRequest
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public int $participationOptionId,
        /** @var array<string, mixed> */
        public array $payload = [],
        public int $adultsCount = 1,
        public int $childrenCount = 0,
        public bool $transferIncluded = false,
        public float $paymentFactor = 1.0,
        public ?int $payNowAmount = null,
    ) {
    }
}
