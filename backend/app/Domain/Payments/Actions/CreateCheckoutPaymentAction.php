<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use RuntimeException;

final class CreateCheckoutPaymentAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    public function execute(Order $order): Payment
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            throw new RuntimeException('Payments can only be created for orders pending payment.');
        }

        /** @var Payment|null $reusable */
        $reusable = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('checkout_url')
            ->orderByDesc('id')
            ->first();

        if ($reusable instanceof Payment && ($reusable->expires_at === null || $reusable->expires_at->isFuture())) {
            return $reusable;
        }

        $amountCentavos = MoneyFormatter::toCentavos($order->total_amount);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'provider' => 'paymongo',
            'status' => PaymentStatus::Pending,
            'currency' => $order->currency,
            'amount' => $order->total_amount,
            'amount_centavos' => $amountCentavos,
            'expires_at' => Date::now()->addHours(2),
        ]);

        if (! $this->gateway->isConfigured()) {
            if (app()->isProduction()) {
                throw new RuntimeException('PayMongo credentials must be configured in production.');
            }

            $sandboxRef = 'cs_sandbox_'.Str::lower(Str::random(18));
            $payment->forceFill([
                'provider_reference' => $sandboxRef,
                'checkout_url' => route('checkout.sandbox', ['order' => $order->id]),
                'metadata' => ['mode' => 'sandbox_simulator'],
            ])->save();

            return $payment->refresh();
        }

        $session = $this->gateway->createCheckoutSession(
            order: $order,
            payment: $payment,
            successUrl: route('checkout.success', ['order' => $order->id]),
            cancelUrl: route('checkout.cancel', ['order' => $order->id]),
        );

        $payment->forceFill([
            'provider_reference' => $session->providerReference,
            'checkout_url' => $session->checkoutUrl,
            'metadata' => ['mode' => config('services.paymongo.mode', 'test')],
        ])->save();

        return $payment->refresh();
    }
}
