<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Orders\Notifications\OrderStatusUpdatedNotification;
use App\Domain\Orders\Services\OrderStateRollup;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TransitionSellerOrderStatusAction
{
    public function __construct(
        private readonly InventoryReservationService $inventoryReservation,
        private readonly OrderStateRollup $stateRollup,
    ) {}

    public function execute(
        SellerOrder $sellerOrder,
        OrderStatus $nextStatus,
        ?User $actor = null,
        ?string $reason = null,
        ?string $shippingCarrier = null,
        ?string $trackingNumber = null,
        bool $asSeller = false,
    ): SellerOrder {
        $transitioned = false;
        $updatedSuborder = null;
        $parentOrder = null;

        DB::transaction(function () use (
            $sellerOrder,
            $nextStatus,
            $actor,
            $reason,
            $shippingCarrier,
            $trackingNumber,
            $asSeller,
            &$transitioned,
            &$updatedSuborder,
            &$parentOrder,
        ): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->with('user')
                ->whereKey($sellerOrder->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var SellerOrder $lockedSuborder */
            $lockedSuborder = SellerOrder::query()
                ->with(['order', 'items'])
                ->whereKey($sellerOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $lockedSuborder->status;
            if ($currentStatus === $nextStatus) {
                $updatedSuborder = $lockedSuborder;
                $parentOrder = $lockedOrder;

                return;
            }

            $isAllowed = $asSeller
                ? $currentStatus->canSellerTransitionTo($nextStatus)
                : $currentStatus->canTransitionTo($nextStatus);

            if (! $isAllowed) {
                throw new InvalidArgumentException(
                    "Invalid suborder transition from {$currentStatus->value} to {$nextStatus->value}.",
                );
            }

            $now = Date::now();
            $updates = $this->stateRollup->buildStatusUpdates(
                nextStatus: $nextStatus,
                now: $now,
                currentPaidAt: $lockedSuborder->paid_at,
                currentShippedAt: $lockedSuborder->shipped_at,
                currentDeliveredAt: $lockedSuborder->delivered_at,
                currentCancelledAt: $lockedSuborder->cancelled_at,
                currentRefundedAt: $lockedSuborder->refunded_at,
                shippingCarrier: $shippingCarrier,
                trackingNumber: $trackingNumber,
                includePaymentStatus: false,
            );

            $lockedSuborder->forceFill($updates)->save();

            if ($currentStatus->releasesReservedStockWhenTransitionedTo($nextStatus)) {
                $this->inventoryReservation->releaseForSellerOrder($lockedSuborder, $actor);
            }

            if ($nextStatus === OrderStatus::Delivered) {
                $this->inventoryReservation->markFulfilledForSellerOrder($lockedSuborder, $actor);
            }

            $baseReason = $reason !== null && trim($reason) !== ''
                ? trim($reason)
                : "Updated to {$nextStatus->label()}.";
            $suborderReason = "{$lockedSuborder->seller_name} ({$lockedSuborder->seller_order_number}): {$baseReason}";

            OrderStatusHistory::query()->create([
                'order_id' => $lockedOrder->id,
                'seller_order_id' => $lockedSuborder->id,
                'actor_id' => $actor?->id,
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'reason' => $suborderReason,
                'created_at' => $now,
            ]);

            $this->stateRollup->syncParentFromSellerOrders(
                lockedOrder: $lockedOrder,
                actor: $actor,
                triggerReason: "Order status rolled up from {$lockedSuborder->seller_name} ({$lockedSuborder->seller_order_number}).",
            );

            $transitioned = true;
            $updatedSuborder = $lockedSuborder->refresh();
            $parentOrder = $lockedOrder->refresh();
        });

        if ($transitioned && $updatedSuborder instanceof SellerOrder && $parentOrder instanceof Order) {
            $parentOrder->user->notify(
                OrderStatusUpdatedNotification::fromSellerOrder($parentOrder, $updatedSuborder, $reason),
            );
        }

        return $updatedSuborder instanceof SellerOrder ? $updatedSuborder : $sellerOrder->refresh();
    }
}
