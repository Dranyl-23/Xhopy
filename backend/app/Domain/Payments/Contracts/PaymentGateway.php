<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\DTOs\CheckoutSessionResult;
use App\Domain\Payments\Models\Payment;

interface PaymentGateway
{
    public function isConfigured(): bool;

    public function createCheckoutSession(
        Order $order,
        Payment $payment,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSessionResult;

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array;

    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader): bool;
}
