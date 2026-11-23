<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Services\MoneyFormatter;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlaceOrderAction
{
    public function __construct(
        private readonly InventoryReservationService $inventoryReservation,
    ) {}

    /**
     * Place an order from the customer's active cart idempotently and reserve inventory atomically.
     *
     * @throws ValidationException
     */
    public function execute(
        User $user,
        CustomerAddress $address,
        string $idempotencyKey,
        ?string $notes = null,
    ): Order {
        if ($address->user_id !== $user->id) {
            abort(403);
        }

        $cleanKey = trim($idempotencyKey);
        if ($cleanKey === '') {
            $cleanKey = (string) Str::uuid();
        }

        return DB::transaction(function () use ($user, $address, $cleanKey, $notes): Order {
            /** @var Order|null $existingOrder */
            $existingOrder = Order::query()
                ->with(['items', 'latestPayment'])
                ->where('user_id', $user->id)
                ->where('idempotency_key', $cleanKey)
                ->lockForUpdate()
                ->first();

            if ($existingOrder instanceof Order) {
                return $existingOrder;
            }

            /** @var Cart|null $cart */
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $cart instanceof Cart) {
                throw ValidationException::withMessages([
                    'cart' => 'Your shopping cart is empty.',
                ]);
            }

            $cartItems = CartItem::query()
                ->where('cart_id', $cart->id)
                ->orderBy('product_id')
                ->orderBy('variant_key')
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Your shopping cart is empty.',
                ]);
            }

            $subtotalCentavos = 0;
            $preparedLines = [];

            foreach ($cartItems as $cartItem) {
                /** @var Product|null $product */
                $product = Product::query()
                    ->with(['category', 'store'])
                    ->whereKey($cartItem->product_id)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $product instanceof Product
                    || ! $product->isStorefrontVisible()
                ) {
                    throw ValidationException::withMessages([
                        'cart' => 'One or more items in your cart are no longer active for checkout.',
                    ]);
                }

                $variant = null;
                if ($cartItem->product_variant_id !== null) {
                    /** @var ProductVariant|null $lockedVariant */
                    $lockedVariant = ProductVariant::query()
                        ->whereKey($cartItem->product_variant_id)
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $lockedVariant instanceof ProductVariant || ! $lockedVariant->is_active) {
                        throw ValidationException::withMessages([
                            'cart' => "Selected option for {$product->name} is no longer available.",
                        ]);
                    }

                    $variant = $lockedVariant;
                }

                $unitPriceStr = $variant !== null ? (string) $variant->price : (string) $product->base_price;
                $unitCentavos = MoneyFormatter::toCentavos($unitPriceStr);
                $lineCentavos = $unitCentavos * $cartItem->quantity;
                $subtotalCentavos += $lineCentavos;

                $preparedLines[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => number_format($unitCentavos / 100, 2, '.', ''),
                    'line_total' => number_format($lineCentavos / 100, 2, '.', ''),
                ];
            }

            $now = Date::now();
            $totalDecimal = number_format($subtotalCentavos / 100, 2, '.', '');
            $orderNumber = 'XHP-'.$now->format('Ymd').'-'.strtoupper(Str::random(6));

            $order = Order::query()->create([
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'customer_address_id' => $address->id,
                'idempotency_key' => $cleanKey,
                'status' => OrderStatus::PendingPayment,
                'payment_status' => PaymentStatus::Pending,
                'currency' => 'PHP',
                'subtotal_amount' => $totalDecimal,
                'shipping_amount' => '0.00',
                'discount_amount' => '0.00',
                'tax_amount' => '0.00',
                'total_amount' => $totalDecimal,
                'shipping_recipient_name' => $address->recipient_name,
                'shipping_phone' => $address->phone,
                'shipping_country_code' => $address->country_code,
                'shipping_province' => $address->province,
                'shipping_city' => $address->city,
                'shipping_barangay' => $address->barangay,
                'shipping_postal_code' => $address->postal_code,
                'shipping_address_line_1' => $address->address_line_1,
                'shipping_address_line_2' => $address->address_line_2,
                'shipping_delivery_notes' => $address->delivery_notes,
                'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'placed_at' => $now,
                'expires_at' => $now->addHours(2),
            ]);

            foreach ($preparedLines as $line) {
                /** @var Product $lineProduct */
                $lineProduct = $line['product'];
                /** @var ProductVariant|null $lineVariant */
                $lineVariant = $line['variant'];

                $this->inventoryReservation->reserveLine(
                    product: $lineProduct,
                    variant: $lineVariant,
                    quantity: $line['quantity'],
                    order: $order,
                    actor: $user,
                );

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $lineProduct->id,
                    'product_variant_id' => $lineVariant?->id,
                    'seller_id' => $lineProduct->seller_id,
                    'seller_name' => $lineProduct->seller_name,
                    'product_name' => $lineProduct->name,
                    'variant_title' => $lineVariant?->title,
                    'sku' => $lineVariant !== null ? $lineVariant->sku : $lineProduct->sku,
                    'currency' => $lineProduct->currency,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);
            }

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'actor_id' => $user->id,
                'from_status' => null,
                'to_status' => OrderStatus::PendingPayment,
                'reason' => 'Order placed at checkout; inventory reserved pending payment confirmation.',
                'created_at' => $now,
            ]);

            CartItem::query()->where('cart_id', $cart->id)->delete();

            return $order->load(['items', 'statusHistory']);
        });
    }
}
