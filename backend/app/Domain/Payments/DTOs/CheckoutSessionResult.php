<?php

namespace App\Domain\Payments\DTOs;

final readonly class CheckoutSessionResult
{
    public function __construct(
        public string $providerReference,
        public string $checkoutUrl,
        public bool $isSandboxSimulation = false,
    ) {}
}
