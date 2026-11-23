<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

final class OrderStateRollup
{
    /**
     * Build status/timestamp updates cleanly for either a parent Order or SellerOrder transition.
     *
     * @return array<string, mixed>
     */
    public function buildStatusUpdates(
        OrderStatus $nextStatus,
        CarbonImmutable $now,
        ?CarbonImmutable $currentPaidAt,
        ?CarbonImmutable $currentShippedAt,
        ?CarbonImmutable $currentDeliveredAt,
        ?CarbonImmutable $currentCancelledAt,
        ?CarbonImmutable $currentRefundedAt,
        ?string $shippingCarrier = null,
        ?string $trackingNumber = null,
        ?PaymentStatus $nextPaymentStatus = null,
        bool $includePaymentStatus = false,
    ): array {
        $updates = [
            'status' => $nextStatus,
        ];

        if ($includePaymentStatus && $nextPaymentStatus !== null) {
            $updates['payment_status'] = $nextPaymentStatus;
        }

        if ($nextStatus === OrderStatus::Paid && $currentPaidAt === null) {
            $updates['paid_at'] = $now;
            if ($includePaymentStatus) {
                $updates['payment_status'] = $nextPaymentStatus ?? PaymentStatus::Paid;
            }
        }

        if ($nextStatus === OrderStatus::Shipped) {
            if ($currentShippedAt === null) {
                $updates['shipped_at'] = $now;
            }

            $cleanCarrier = is_string($shippingCarrier) ? trim($shippingCarrier) : '';
            $cleanTracking = is_string($trackingNumber) ? trim($trackingNumber) : '';
            if ($cleanCarrier !== '') {
                $updates['shipping_carrier'] = mb_substr($cleanCarrier, 0, 80);
            }
            if ($cleanTracking !== '') {
                $updates['tracking_number'] = mb_substr($cleanTracking, 0, 120);
            }
        }

        if ($nextStatus === OrderStatus::Delivered && $currentDeliveredAt === null) {
            $updates['delivered_at'] = $now;
        }

        if ($nextStatus === OrderStatus::Refunded) {
            if ($currentRefundedAt === null) {
                $updates['refunded_at'] = $now;
            }
            if ($includePaymentStatus) {
                $updates['payment_status'] = $nextPaymentStatus ?? PaymentStatus::Refunded;
            }
        }

        if (in_array($nextStatus, [OrderStatus::Cancelled, OrderStatus::Expired], true) && $currentCancelledAt === null) {
            $updates['cancelled_at'] = $now;
        }

        return $updates;
    }

    /**
     * Recompute deterministic parent order status & timestamps from child seller_orders inside an active transaction.
     */
    public function syncParentFromSellerOrders(Order $lockedOrder, ?User $actor, string $triggerReason): ?OrderStatus
    {
        $suborders = SellerOrder::query()
            ->where('order_id', $lockedOrder->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($suborders->isEmpty()) {
            return null;
        }

        $active = $suborders->filter(
            static fn (SellerOrder $suborder): bool => ! in_array(
                $suborder->status,
                [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Expired],
                true,
            ),
        );

        $now = Date::now()->toImmutable();
        $previousParentStatus = $lockedOrder->status;
        $targetParentPaymentStatus = null;

        if ($active->isEmpty()) {
            if ($suborders->contains(static fn (SellerOrder $s): bool => $s->status === OrderStatus::Refunded)) {
                $targetParentStatus = OrderStatus::Refunded;
                $targetParentPaymentStatus = PaymentStatus::Refunded;
            } elseif ($suborders->contains(static fn (SellerOrder $s): bool => $s->status === OrderStatus::Cancelled)) {
                $targetParentStatus = OrderStatus::Cancelled;
            } else {
                $targetParentStatus = OrderStatus::Expired;
            }
        } elseif ($active->every(static fn (SellerOrder $s): bool => $s->status === OrderStatus::PendingPayment)) {
            $targetParentStatus = OrderStatus::PendingPayment;
        } elseif ($active->every(static fn (SellerOrder $s): bool => $s->status === OrderStatus::Delivered)) {
            $targetParentStatus = OrderStatus::Delivered;
        } elseif ($active->every(static fn (SellerOrder $s): bool => in_array($s->status, [OrderStatus::Shipped, OrderStatus::Delivered], true))) {
            $targetParentStatus = OrderStatus::Shipped;
        } elseif ($active->contains(static fn (SellerOrder $s): bool => in_array($s->status, [OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered], true))) {
            $targetParentStatus = OrderStatus::Processing;
        } else {
            $targetParentStatus = OrderStatus::Paid;
        }

        if ($previousParentStatus === $targetParentStatus) {
            return null;
        }

        $singleCarrier = null;
        $singleTracking = null;
        if ($suborders->count() === 1) {
            /** @var SellerOrder $onlySuborder */
            $onlySuborder = $suborders->first();
            $singleCarrier = $onlySuborder->shipping_carrier;
            $singleTracking = $onlySuborder->tracking_number;
        }

        $updates = $this->buildStatusUpdates(
            nextStatus: $targetParentStatus,
            now: $now,
            currentPaidAt: $lockedOrder->paid_at,
            currentShippedAt: $lockedOrder->shipped_at,
            currentDeliveredAt: $lockedOrder->delivered_at,
            currentCancelledAt: $lockedOrder->cancelled_at,
            currentRefundedAt: $lockedOrder->refunded_at,
            shippingCarrier: $singleCarrier,
            trackingNumber: $singleTracking,
            nextPaymentStatus: $targetParentPaymentStatus,
            includePaymentStatus: true,
        );

        $lockedOrder->forceFill($updates)->save();

        OrderStatusHistory::query()->create([
            'order_id' => $lockedOrder->id,
            'seller_order_id' => null,
            'actor_id' => $actor?->id,
            'from_status' => $previousParentStatus,
            'to_status' => $targetParentStatus,
            'reason' => $triggerReason,
            'created_at' => $now,
        ]);

        return $targetParentStatus;
    }
}
