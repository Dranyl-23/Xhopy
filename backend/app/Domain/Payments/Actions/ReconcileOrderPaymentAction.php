<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReconcileOrderPaymentAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly TransitionOrderStatusAction $transitionOrder,
    ) {}

    /**
     * Reconcile a pending order by querying PayMongo server-side rather than trusting redirect URLs.
     */
    public function execute(Order $order): Order
    {
        if ($order->status !== OrderStatus::PendingPayment || ! $this->gateway->isConfigured()) {
            return $order;
        }

        /** @var Payment|null $payment */
        $payment = $order->latestPayment()->first();
        if (! $payment instanceof Payment || $payment->provider_reference === null) {
            return $order;
        }

        try {
            $sessionData = $this->gateway->retrieveCheckoutSession($payment->provider_reference);
        } catch (Throwable) {
            return $order;
        }

        $payments = (array) data_get($sessionData, 'attributes.payments', []);
        $paidPayment = null;

        foreach ($payments as $entry) {
            if (is_array($entry) && data_get($entry, 'attributes.status') === 'paid') {
                $paidPayment = $entry;
                break;
            }
        }

        if ($paidPayment === null) {
            return $order;
        }

        return DB::transaction(function () use ($order, $payment, $paidPayment): Order {
            $paymentId = is_string($paidPayment['id'] ?? null) ? $paidPayment['id'] : null;
            $methodType = is_string(data_get($paidPayment, 'attributes.source.type'))
                ? (string) data_get($paidPayment, 'attributes.source.type')
                : null;

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'provider_payment_id' => $paymentId ?? $payment->provider_payment_id,
                'payment_method_type' => $methodType ?? $payment->payment_method_type,
                'paid_at' => Date::now(),
            ])->save();

            return $this->transitionOrder->execute(
                order: $order,
                nextStatus: OrderStatus::Paid,
                actor: null,
                reason: 'Confirmed via PayMongo checkout session reconciliation.',
                nextPaymentStatus: PaymentStatus::Paid,
            );
        });
    }
}
