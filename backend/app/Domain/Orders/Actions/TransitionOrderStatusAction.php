<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Orders\Notifications\OrderStatusUpdatedNotification;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TransitionOrderStatusAction
{
    public function __construct(
        private readonly InventoryReservationService $inventoryReservation,
    ) {}

    public function execute(
        Order $order,
        OrderStatus $nextStatus,
        ?User $actor = null,
        ?string $reason = null,
        ?PaymentStatus $nextPaymentStatus = null,
        ?string $shippingCarrier = null,
        ?string $trackingNumber = null,
    ): Order {
        $transitionedFrom = null;

        $updatedOrder = DB::transaction(function () use (
            $order,
            $nextStatus,
            $actor,
            $reason,
            $nextPaymentStatus,
            $shippingCarrier,
            $trackingNumber,
            &$transitionedFrom,
        ): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with('user')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $lockedOrder->status;

            if ($currentStatus === $nextStatus) {
                return $lockedOrder;
            }

            if (! $currentStatus->canTransitionTo($nextStatus)) {
                throw new InvalidArgumentException("Invalid order transition from {$currentStatus->value} to {$nextStatus->value}.");
            }

            $now = Date::now();
            $updates = [
                'status' => $nextStatus,
            ];

            if ($nextPaymentStatus !== null) {
                $updates['payment_status'] = $nextPaymentStatus;
            }

            if ($nextStatus === OrderStatus::Paid && $lockedOrder->paid_at === null) {
                $updates['paid_at'] = $now;
                $updates['payment_status'] = $nextPaymentStatus ?? PaymentStatus::Paid;
            }

            if ($nextStatus === OrderStatus::Shipped) {
                if ($lockedOrder->shipped_at === null) {
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

            if ($nextStatus === OrderStatus::Delivered && $lockedOrder->delivered_at === null) {
                $updates['delivered_at'] = $now;
            }

            if ($nextStatus === OrderStatus::Refunded) {
                if ($lockedOrder->refunded_at === null) {
                    $updates['refunded_at'] = $now;
                }
                $updates['payment_status'] = $nextPaymentStatus ?? PaymentStatus::Refunded;
            }

            if (in_array($nextStatus, [OrderStatus::Cancelled, OrderStatus::Expired], true) && $lockedOrder->cancelled_at === null) {
                $updates['cancelled_at'] = $now;
            }

            $lockedOrder->forceFill($updates)->save();

            if ($currentStatus->releasesReservedStockWhenTransitionedTo($nextStatus)) {
                $this->inventoryReservation->releaseForOrder($lockedOrder, $actor);
            }

            if ($nextStatus === OrderStatus::Delivered) {
                $this->inventoryReservation->markFulfilledForOrder($lockedOrder, $actor);
            }

            OrderStatusHistory::query()->create([
                'order_id' => $lockedOrder->id,
                'actor_id' => $actor?->id,
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'reason' => $reason,
                'created_at' => $now,
            ]);

            $transitionedFrom = $currentStatus;

            return $lockedOrder->refresh();
        });

        if ($transitionedFrom !== null) {
            $updatedOrder->user->notify(OrderStatusUpdatedNotification::fromOrder($updatedOrder, $reason));
        }

        return $updatedOrder;
    }
}
