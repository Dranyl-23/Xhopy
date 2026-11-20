<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Orders\Actions\TransitionOrderStatusAction;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class ProcessPayMongoWebhookAction
{
    public function __construct(
        private readonly TransitionOrderStatusAction $transitionOrder,
    ) {}

    /**
     * Process a signature-verified PayMongo webhook payload idempotently.
     *
     * @param  array<string, mixed>  $payload
     * @return 'already_processed'|'ignored'|'processed'
     */
    public function execute(array $payload): string
    {
        $eventId = data_get($payload, 'data.id');
        $eventType = data_get($payload, 'data.attributes.type');

        if (! is_string($eventId) || trim($eventId) === '' || ! is_string($eventType) || trim($eventType) === '') {
            return 'ignored';
        }

        $cleanEventId = trim($eventId);
        $cleanEventType = trim($eventType);
        $resourceData = (array) data_get($payload, 'data.attributes.data', []);
        $sanitizedPayload = self::sanitizeEventPayload($cleanEventType, $resourceData);

        try {
            return DB::transaction(function () use ($cleanEventId, $cleanEventType, $resourceData, $sanitizedPayload): string {
                if (PaymentEvent::query()->where('provider_event_id', $cleanEventId)->lockForUpdate()->exists()) {
                    return 'already_processed';
                }

                $payment = $this->resolvePayment($resourceData);
                $order = $this->resolveOrder($resourceData, $payment);
                $now = Date::now();

                PaymentEvent::query()->create([
                    'payment_id' => $payment?->id,
                    'order_id' => $order?->id,
                    'provider' => 'paymongo',
                    'provider_event_id' => $cleanEventId,
                    'event_type' => $cleanEventType,
                    'status' => $order instanceof Order ? 'processed' : 'unmatched',
                    'payload' => $sanitizedPayload,
                    'processed_at' => $now,
                    'created_at' => $now,
                ]);

                if (! $order instanceof Order) {
                    return 'ignored';
                }

                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                $paymentId = self::extractProviderPaymentId($resourceData);
                $methodType = self::extractPaymentMethodType($resourceData);

                match ($cleanEventType) {
                    'checkout_session.payment.paid',
                    'payment.paid' => $this->handlePaid($lockedOrder, $payment, $paymentId, $methodType),
                    'payment.failed' => $this->handleFailed($lockedOrder, $payment, $paymentId),
                    'checkout_session.expired' => $this->handleExpired($lockedOrder, $payment),
                    'payment.refunded' => $this->handleRefunded($lockedOrder, $payment),
                    default => null,
                };

                return 'processed';
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return 'already_processed';
            }

            throw $exception;
        }
    }

    private function handlePaid(Order $order, ?Payment $payment, ?string $providerPaymentId, ?string $methodType): void
    {
        $now = Date::now();

        if ($payment instanceof Payment) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if ($lockedPayment instanceof Payment && $lockedPayment->status !== PaymentStatus::Paid) {
                $lockedPayment->forceFill([
                    'status' => PaymentStatus::Paid,
                    'provider_payment_id' => $providerPaymentId ?? $lockedPayment->provider_payment_id,
                    'payment_method_type' => $methodType ?? $lockedPayment->payment_method_type,
                    'paid_at' => $now,
                ])->save();
            }
        }

        if ($order->status === OrderStatus::PendingPayment) {
            $this->transitionOrder->execute(
                order: $order,
                nextStatus: OrderStatus::Paid,
                actor: null,
                reason: 'Confirmed via verified PayMongo payment webhook.',
                nextPaymentStatus: PaymentStatus::Paid,
            );
        }
    }

    private function handleFailed(Order $order, ?Payment $payment, ?string $providerPaymentId): void
    {
        if ($payment instanceof Payment) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if ($lockedPayment instanceof Payment && $lockedPayment->status === PaymentStatus::Pending) {
                $lockedPayment->forceFill([
                    'status' => PaymentStatus::Failed,
                    'provider_payment_id' => $providerPaymentId ?? $lockedPayment->provider_payment_id,
                ])->save();
            }
        }

        if ($order->status === OrderStatus::PendingPayment) {
            $order->forceFill(['payment_status' => PaymentStatus::Failed])->save();
        }
    }

    private function handleExpired(Order $order, ?Payment $payment): void
    {
        if ($payment instanceof Payment) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if ($lockedPayment instanceof Payment && $lockedPayment->status === PaymentStatus::Pending) {
                $lockedPayment->forceFill(['status' => PaymentStatus::Expired])->save();
            }
        }

        if ($order->status === OrderStatus::PendingPayment) {
            $this->transitionOrder->execute(
                order: $order,
                nextStatus: OrderStatus::Expired,
                actor: null,
                reason: 'Checkout session expired; reserved inventory released.',
                nextPaymentStatus: PaymentStatus::Expired,
            );
        }
    }

    private function handleRefunded(Order $order, ?Payment $payment): void
    {
        if ($payment instanceof Payment) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
            if ($lockedPayment instanceof Payment && $lockedPayment->status === PaymentStatus::Paid) {
                $lockedPayment->forceFill(['status' => PaymentStatus::Refunded])->save();
            }
        }

        if ($order->status->canTransitionTo(OrderStatus::Refunded)) {
            $this->transitionOrder->execute(
                order: $order,
                nextStatus: OrderStatus::Refunded,
                actor: null,
                reason: 'Refund confirmed via verified PayMongo webhook.',
                nextPaymentStatus: PaymentStatus::Refunded,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $resourceData
     */
    private function resolvePayment(array $resourceData): ?Payment
    {
        $resourceId = data_get($resourceData, 'id');
        if (is_string($resourceId) && $resourceId !== '') {
            $byReference = Payment::query()->where('provider_reference', $resourceId)->first();
            if ($byReference instanceof Payment) {
                return $byReference;
            }
        }

        $paymentId = data_get($resourceData, 'attributes.metadata.payment_id');
        if (is_scalar($paymentId) && ctype_digit((string) $paymentId)) {
            return Payment::query()->find((int) $paymentId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resourceData
     */
    private function resolveOrder(array $resourceData, ?Payment $payment): ?Order
    {
        if ($payment instanceof Payment) {
            return Order::query()->find($payment->order_id);
        }

        $orderId = data_get($resourceData, 'attributes.metadata.order_id');
        if (is_scalar($orderId) && ctype_digit((string) $orderId)) {
            return Order::query()->find((int) $orderId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resourceData
     */
    private static function extractProviderPaymentId(array $resourceData): ?string
    {
        $firstPaymentId = data_get($resourceData, 'attributes.payments.0.id');
        if (is_string($firstPaymentId) && $firstPaymentId !== '') {
            return $firstPaymentId;
        }

        $resourceType = data_get($resourceData, 'type');
        $resourceId = data_get($resourceData, 'id');

        if ($resourceType === 'payment' && is_string($resourceId) && $resourceId !== '') {
            return $resourceId;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resourceData
     */
    private static function extractPaymentMethodType(array $resourceData): ?string
    {
        $fromNested = data_get($resourceData, 'attributes.payments.0.attributes.source.type');
        if (is_string($fromNested) && $fromNested !== '') {
            return $fromNested;
        }

        $fromDirect = data_get($resourceData, 'attributes.source.type');
        if (is_string($fromDirect) && $fromDirect !== '') {
            return $fromDirect;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resourceData
     * @return array<string, mixed>
     */
    private static function sanitizeEventPayload(string $eventType, array $resourceData): array
    {
        return [
            'event_type' => $eventType,
            'resource_id' => is_string($resourceData['id'] ?? null) ? $resourceData['id'] : null,
            'resource_type' => is_string($resourceData['type'] ?? null) ? $resourceData['type'] : null,
            'reference_number' => data_get($resourceData, 'attributes.reference_number'),
            'status' => data_get($resourceData, 'attributes.status'),
        ];
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        return $sqlState === '23000' || $sqlState === '23505';
    }
}
