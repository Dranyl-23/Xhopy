<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Finance\Services\SellerFinanceService;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Orders\Notifications\OrderStatusUpdatedNotification;
use App\Domain\Orders\Services\OrderStateRollup;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TransitionOrderStatusAction
{
    public function __construct(
        private readonly InventoryReservationService $inventoryReservation,
        private readonly OrderStateRollup $stateRollup,
        private readonly SellerFinanceService $financeService,
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
            $suborders = SellerOrder::query()
                ->with(['order', 'items'])
                ->where('order_id', $lockedOrder->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($suborders->isEmpty()) {
                $updates = $this->stateRollup->buildStatusUpdates(
                    nextStatus: $nextStatus,
                    now: $now,
                    currentPaidAt: $lockedOrder->paid_at,
                    currentShippedAt: $lockedOrder->shipped_at,
                    currentDeliveredAt: $lockedOrder->delivered_at,
                    currentCancelledAt: $lockedOrder->cancelled_at,
                    currentRefundedAt: $lockedOrder->refunded_at,
                    shippingCarrier: $shippingCarrier,
                    trackingNumber: $trackingNumber,
                    nextPaymentStatus: $nextPaymentStatus,
                    includePaymentStatus: true,
                );

                $lockedOrder->forceFill($updates)->save();

                if ($currentStatus->releasesReservedStockWhenTransitionedTo($nextStatus)) {
                    $this->inventoryReservation->releaseForOrder($lockedOrder, $actor);
                }

                if ($nextStatus === OrderStatus::Delivered) {
                    $this->inventoryReservation->markFulfilledForOrder($lockedOrder, $actor);
                }

                OrderStatusHistory::query()->create([
                    'order_id' => $lockedOrder->id,
                    'seller_order_id' => null,
                    'actor_id' => $actor?->id,
                    'from_status' => $currentStatus,
                    'to_status' => $nextStatus,
                    'reason' => $reason,
                    'created_at' => $now,
                ]);
            } else {
                foreach ($suborders as $suborder) {
                    $subPreviousStatus = $suborder->status;
                    if ($subPreviousStatus === $nextStatus || ! $subPreviousStatus->canTransitionTo($nextStatus)) {
                        continue;
                    }

                    $subUpdates = $this->stateRollup->buildStatusUpdates(
                        nextStatus: $nextStatus,
                        now: $now,
                        currentPaidAt: $suborder->paid_at,
                        currentShippedAt: $suborder->shipped_at,
                        currentDeliveredAt: $suborder->delivered_at,
                        currentCancelledAt: $suborder->cancelled_at,
                        currentRefundedAt: $suborder->refunded_at,
                        shippingCarrier: $shippingCarrier,
                        trackingNumber: $trackingNumber,
                        includePaymentStatus: false,
                    );

                    $suborder->forceFill($subUpdates)->save();

                    if ($nextStatus === OrderStatus::Paid) {
                        $this->financeService->recordSuborderSettled($suborder, $actor);
                    }

                    if ($subPreviousStatus->releasesReservedStockWhenTransitionedTo($nextStatus)) {
                        $this->inventoryReservation->releaseForSellerOrder($suborder, $actor);
                    }

                    if ($nextStatus === OrderStatus::Delivered) {
                        $this->inventoryReservation->markFulfilledForSellerOrder($suborder, $actor);
                        $this->financeService->recordSuborderDelivered($suborder->refresh());
                    }

                    if (in_array($nextStatus, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
                        $this->financeService->recordSuborderReversed($suborder, $actor, $reason);
                    }

                    $subReason = $reason !== null && trim($reason) !== ''
                        ? "{$suborder->seller_name} ({$suborder->seller_order_number}): ".trim($reason)
                        : "{$suborder->seller_name} ({$suborder->seller_order_number}): Updated to {$nextStatus->label()}.";

                    OrderStatusHistory::query()->create([
                        'order_id' => $lockedOrder->id,
                        'seller_order_id' => $suborder->id,
                        'actor_id' => $actor?->id,
                        'from_status' => $subPreviousStatus,
                        'to_status' => $nextStatus,
                        'reason' => $subReason,
                        'created_at' => $now,
                    ]);
                }

                $rolledStatus = $this->stateRollup->syncParentFromSellerOrders(
                    lockedOrder: $lockedOrder,
                    actor: $actor,
                    triggerReason: $reason ?? "Order updated to {$nextStatus->label()}.",
                );

                if ($rolledStatus === null) {
                    return $lockedOrder->refresh();
                }

                if ($nextPaymentStatus !== null && $lockedOrder->fresh()?->payment_status !== $nextPaymentStatus) {
                    $lockedOrder->forceFill(['payment_status' => $nextPaymentStatus])->save();
                }
            }

            $transitionedFrom = $currentStatus;

            return $lockedOrder->refresh();
        });

        if ($transitionedFrom !== null) {
            $updatedOrder->user->notify(OrderStatusUpdatedNotification::fromOrder($updatedOrder, $reason));
        }

        return $updatedOrder;
    }
}
