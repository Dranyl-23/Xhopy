<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryReservationService
{
    /**
     * Atomically reserve inventory for a single product or variant inside an active transaction.
     *
     * @throws ValidationException
     */
    public function reserveLine(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        Order $order,
        ?User $actor = null,
    ): void {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'cart' => 'Order line quantity must be at least 1.',
            ]);
        }

        if ($variant instanceof ProductVariant) {
            $updated = ProductVariant::query()
                ->whereKey($variant->id)
                ->where('stock_quantity', '>=', $quantity)
                ->decrement('stock_quantity', $quantity);

            if ($updated === 0) {
                throw ValidationException::withMessages([
                    'cart' => "Insufficient stock for {$product->name} ({$variant->title}).",
                ]);
            }

            $freshVariant = $variant->refresh();

            StockMovement::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'store_id' => $product->store_id,
                'order_id' => $order->id,
                'actor_id' => $actor?->id,
                'type' => StockMovementType::Reserved,
                'quantity_delta' => -$quantity,
                'stock_after' => (int) $freshVariant->stock_quantity,
                'reference' => $order->order_number,
                'created_at' => Date::now(),
            ]);

            $this->syncParentProductStockFromVariants($product->id);

            return;
        }

        $updated = Product::query()
            ->whereKey($product->id)
            ->where('stock_quantity', '>=', $quantity)
            ->decrement('stock_quantity', $quantity);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'cart' => "Insufficient stock for {$product->name}.",
            ]);
        }

        $freshProduct = $product->refresh();

        StockMovement::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'store_id' => $freshProduct->store_id,
            'order_id' => $order->id,
            'actor_id' => $actor?->id,
            'type' => StockMovementType::Reserved,
            'quantity_delta' => -$quantity,
            'stock_after' => (int) $freshProduct->stock_quantity,
            'reference' => $order->order_number,
            'created_at' => Date::now(),
        ]);
    }

    /**
     * Atomically release reserved inventory back when an unpaid order cancels or expires.
     */
    public function releaseForOrder(Order $order, ?User $actor = null): void
    {
        $items = $order->items()->orderBy('product_id')->orderBy('product_variant_id')->get();
        $this->releaseItems($items, $order, $actor, $order->order_number);
    }

    /**
     * Atomically release reserved inventory strictly belonging to a specific seller suborder.
     */
    public function releaseForSellerOrder(SellerOrder $sellerOrder, ?User $actor = null): void
    {
        $sellerOrder->loadMissing('order');
        $items = $sellerOrder->items()->orderBy('product_id')->orderBy('product_variant_id')->get();
        $this->releaseItems($items, $sellerOrder->order, $actor, $sellerOrder->seller_order_number);
    }

    /**
     * @param  iterable<int, OrderItem>  $items
     */
    private function releaseItems(iterable $items, Order $order, ?User $actor, string $reference): void
    {
        $now = Date::now();

        foreach ($items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            if ($item->product_variant_id !== null) {
                ProductVariant::query()
                    ->whereKey($item->product_variant_id)
                    ->increment('stock_quantity', $item->quantity);

                $freshVariant = ProductVariant::query()->with('product')->find($item->product_variant_id);
                if ($freshVariant instanceof ProductVariant) {
                    StockMovement::query()->create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $freshVariant->id,
                        'store_id' => $freshVariant->product->store_id,
                        'order_id' => $order->id,
                        'actor_id' => $actor?->id,
                        'type' => StockMovementType::Released,
                        'quantity_delta' => $item->quantity,
                        'stock_after' => (int) $freshVariant->stock_quantity,
                        'reference' => $reference,
                        'created_at' => $now,
                    ]);
                }

                $this->syncParentProductStockFromVariants($item->product_id);

                continue;
            }

            Product::query()
                ->whereKey($item->product_id)
                ->increment('stock_quantity', $item->quantity);

            $freshProduct = Product::query()->find($item->product_id);
            if ($freshProduct instanceof Product) {
                StockMovement::query()->create([
                    'product_id' => $freshProduct->id,
                    'product_variant_id' => null,
                    'store_id' => $freshProduct->store_id,
                    'order_id' => $order->id,
                    'actor_id' => $actor?->id,
                    'type' => StockMovementType::Released,
                    'quantity_delta' => $item->quantity,
                    'stock_after' => (int) $freshProduct->stock_quantity,
                    'reference' => $reference,
                    'created_at' => $now,
                ]);
            }
        }
    }

    private function syncParentProductStockFromVariants(int $productId): void
    {
        $activeSum = (int) ProductVariant::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->sum('stock_quantity');

        Product::query()
            ->whereKey($productId)
            ->update(['stock_quantity' => $activeSum]);
    }

    /**
     * Record zero-delta fulfillment milestone ledger entries when an order reaches delivery completion.
     */
    public function markFulfilledForOrder(Order $order, ?User $actor = null): void
    {
        $items = $order->items()->orderBy('product_id')->orderBy('product_variant_id')->get();
        $this->markFulfilledItems($items, $order, $actor, $order->order_number);
    }

    /**
     * Record a zero-delta fulfillment milestone ledger entry for an individual seller suborder.
     */
    public function markFulfilledForSellerOrder(SellerOrder $sellerOrder, ?User $actor = null): void
    {
        $sellerOrder->loadMissing('order');
        $items = $sellerOrder->items()->orderBy('product_id')->orderBy('product_variant_id')->get();
        $this->markFulfilledItems($items, $sellerOrder->order, $actor, $sellerOrder->seller_order_number);
    }

    /**
     * @param  iterable<int, OrderItem>  $items
     */
    private function markFulfilledItems(iterable $items, Order $order, ?User $actor, string $reference): void
    {
        $alreadyRecorded = StockMovement::query()
            ->where('order_id', $order->id)
            ->where('type', StockMovementType::Fulfilled->value)
            ->where('reference', $reference)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $now = Date::now();

        foreach ($items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            if ($item->product_variant_id !== null) {
                $variant = ProductVariant::query()->with('product')->find($item->product_variant_id);
                if ($variant instanceof ProductVariant) {
                    StockMovement::query()->create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $variant->id,
                        'store_id' => $variant->product->store_id,
                        'order_id' => $order->id,
                        'actor_id' => $actor?->id,
                        'type' => StockMovementType::Fulfilled,
                        'quantity_delta' => 0,
                        'stock_after' => (int) $variant->stock_quantity,
                        'reference' => $reference,
                        'created_at' => $now,
                    ]);
                }

                continue;
            }

            $product = Product::query()->find($item->product_id);
            if ($product instanceof Product) {
                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'store_id' => $product->store_id,
                    'order_id' => $order->id,
                    'actor_id' => $actor?->id,
                    'type' => StockMovementType::Fulfilled,
                    'quantity_delta' => 0,
                    'stock_after' => (int) $product->stock_quantity,
                    'reference' => $reference,
                    'created_at' => $now,
                ]);
            }
        }
    }

    /**
     * Atomically adjust inventory count to a verified non-negative quantity and log a movement entry.
     *
     * @throws ValidationException
     */
    public function adjustStock(
        Product $product,
        ?ProductVariant $variant,
        int $targetQuantity,
        User $actor,
        string $reference = 'Manual inventory adjustment',
    ): StockMovement {
        if ($targetQuantity < 0) {
            throw ValidationException::withMessages([
                'stock_quantity' => 'Stock quantity cannot be negative.',
            ]);
        }

        $cleanRef = mb_substr(trim($reference) !== '' ? trim($reference) : 'Manual inventory adjustment', 0, 120);

        return DB::transaction(function () use ($product, $variant, $targetQuantity, $actor, $cleanRef): StockMovement {
            $now = Date::now();

            if ($variant instanceof ProductVariant) {
                /** @var ProductVariant $lockedVariant */
                $lockedVariant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->whereKey($variant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $previous = (int) $lockedVariant->stock_quantity;
                $delta = $targetQuantity - $previous;

                $lockedVariant->forceFill(['stock_quantity' => $targetQuantity])->save();

                $movement = StockMovement::query()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $lockedVariant->id,
                    'store_id' => $product->store_id,
                    'order_id' => null,
                    'actor_id' => $actor->id,
                    'type' => StockMovementType::Adjusted,
                    'quantity_delta' => $delta,
                    'stock_after' => $targetQuantity,
                    'reference' => $cleanRef,
                    'created_at' => $now,
                ]);

                $this->syncParentProductStockFromVariants($product->id);

                return $movement;
            }

            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProduct->variants()->exists()) {
                throw ValidationException::withMessages([
                    'stock_quantity' => 'Products with variants must have stock adjusted per variant SKU.',
                ]);
            }

            $previous = (int) $lockedProduct->stock_quantity;
            $delta = $targetQuantity - $previous;

            $lockedProduct->forceFill(['stock_quantity' => $targetQuantity])->save();

            return StockMovement::query()->create([
                'product_id' => $lockedProduct->id,
                'product_variant_id' => null,
                'store_id' => $lockedProduct->store_id,
                'order_id' => null,
                'actor_id' => $actor->id,
                'type' => StockMovementType::Adjusted,
                'quantity_delta' => $delta,
                'stock_after' => $targetQuantity,
                'reference' => $cleanRef,
                'created_at' => $now,
            ]);
        });
    }
}
