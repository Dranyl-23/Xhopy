<?php

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Enums\StaffPermission;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\SellerOrder;
use App\Domain\Orders\Notifications\OrderStatusUpdatedNotification;
use App\Domain\Payments\Actions\ProcessPayMongoWebhookAction;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\Store;
use App\Domain\Seller\Models\StoreMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, profile: SellerProfile, store: Store}
 */
function createMv5SellerWorkspace(string $name, string $slug, SellerStatus $sellerStatus = SellerStatus::Approved, StoreStatus $storeStatus = StoreStatus::Active): array
{
    $user = User::factory()->seller()->create();
    $store = Store::query()->create([
        'owner_user_id' => $user->id,
        'name' => $name,
        'slug' => $slug,
        'description' => "{$name} verified storefront.",
        'contact_email' => "{$slug}@xhopy.example",
        'contact_phone' => '+63 917 555 0100',
        'status' => $storeStatus,
        'is_official' => false,
    ]);

    StoreMembership::query()->create([
        'store_id' => $store->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    $profile = SellerProfile::query()->create([
        'user_id' => $user->id,
        'store_id' => $store->id,
        'business_name' => "{$name} OPC",
        'business_type' => 'sole_proprietor',
        'contact_phone' => '+63 917 555 0100',
        'business_address' => '120 Ayala Ave, Makati City',
        'status' => $sellerStatus,
        'submitted_at' => now()->subDays(2),
        'reviewed_at' => now()->subDay(),
        'approved_at' => $sellerStatus === SellerStatus::Approved ? now()->subDay() : null,
    ]);

    return compact('user', 'profile', 'store');
}

test('mixed-seller checkout splits parent order into reconciled seller_orders idempotently and unpaid cancel releases all stock', function (): void {
    config(['services.paymongo.secret_key' => '']);

    $category = Category::factory()->create(['is_active' => true]);
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);

    $officialStore = Store::query()->where('slug', 'xhopy-official')->firstOrFail();
    ['store' => $coffeeStore] = createMv5SellerWorkspace('Manila Roast Coffee', 'manila-roast-coffee');

    $keyboard = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $officialStore->id,
        'seller_name' => $officialStore->name,
        'name' => 'XHopy Flagship Keyboard',
        'base_price' => '3200.00',
        'stock_quantity' => 8,
        'status' => ProductStatus::Active,
    ]);

    $beans = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $coffeeStore->id,
        'seller_name' => $coffeeStore->name,
        'name' => 'Benguet Single Origin Beans',
        'base_price' => '650.00',
        'stock_quantity' => 10,
        'status' => ProductStatus::Active,
    ]);

    $this->actingAs($customer)->post(route('cart.items.store'), [
        'product_id' => $keyboard->id,
        'quantity' => 1,
    ]);
    $this->actingAs($customer)->post(route('cart.items.store'), [
        'product_id' => $beans->id,
        'quantity' => 2,
    ]);

    $idempotencyKey = 'mv5-mixed-checkout-001';

    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => $idempotencyKey,
        ])
        ->assertRedirect();

    // Replaying exact same idempotency_key must not split duplicate orders or double reserve stock.
    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => $idempotencyKey,
        ])
        ->assertRedirect();

    expect(Order::query()->count())->toBe(1)
        ->and(SellerOrder::query()->count())->toBe(2);

    $order = Order::query()->with(['sellerOrders.items', 'items'])->firstOrFail();
    expect((string) $order->subtotal_amount)->toBe('4500.00')
        ->and((string) $order->shipping_amount)->toBe('0.00')
        ->and((string) $order->total_amount)->toBe('4500.00');

    $suborderA = $order->sellerOrders->firstWhere('store_id', $officialStore->id);
    $suborderB = $order->sellerOrders->firstWhere('store_id', $coffeeStore->id);

    expect($suborderA)->not->toBeNull()
        ->and($suborderB)->not->toBeNull()
        ->and($suborderA->seller_order_number)->toBe("{$order->order_number}-S1")
        ->and($suborderB->seller_order_number)->toBe("{$order->order_number}-S2")
        ->and((string) $suborderA->total_amount)->toBe('3200.00')
        ->and((string) $suborderB->total_amount)->toBe('1300.00')
        ->and($suborderA->items)->toHaveCount(1)
        ->and($suborderB->items)->toHaveCount(1)
        ->and($suborderB->items->first()?->store_id)->toBe($coffeeStore->id)
        ->and($keyboard->fresh()?->stock_quantity)->toBe(7)
        ->and($beans->fresh()?->stock_quantity)->toBe(8);

    // Pre-payment customer cancellation cancels parent + all suborders and restores reserved stock.
    $this->actingAs($customer)
        ->post(route('orders.cancel', $order))
        ->assertRedirect();

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled)
        ->and($suborderA->fresh()?->status)->toBe(OrderStatus::Cancelled)
        ->and($suborderB->fresh()?->status)->toBe(OrderStatus::Cancelled)
        ->and($keyboard->fresh()?->stock_quantity)->toBe(8)
        ->and($beans->fresh()?->stock_quantity)->toBe(10);
});

test('paymongo payment webhook updates all seller_orders, allows independent seller fulfillment rollup, and enforces strict cross-seller isolation', function (): void {
    Notification::fake();

    $category = Category::factory()->create(['is_active' => true]);
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);

    ['user' => $sellerA, 'store' => $storeA] = createMv5SellerWorkspace('Alpha Roasters', 'alpha-roasters');
    ['user' => $sellerB, 'store' => $storeB] = createMv5SellerWorkspace('Beta Electronics', 'beta-electronics');

    $productA = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeA->id,
        'seller_name' => $storeA->name,
        'name' => 'Alpha Cold Brew carafe',
        'base_price' => '1500.00',
        'stock_quantity' => 10,
        'status' => ProductStatus::Active,
    ]);

    $productB = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeB->id,
        'seller_name' => $storeB->name,
        'name' => 'Beta Mechanical Mouse',
        'base_price' => '900.00',
        'stock_quantity' => 10,
        'status' => ProductStatus::Active,
    ]);

    $this->actingAs($customer)->post(route('cart.items.store'), [
        'product_id' => $productA->id,
        'quantity' => 1,
    ]);
    $this->actingAs($customer)->post(route('cart.items.store'), [
        'product_id' => $productB->id,
        'quantity' => 1,
    ]);

    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => 'mv5-fulfillment-order-01',
        ])
        ->assertRedirect();

    $order = Order::query()->with('sellerOrders')->firstOrFail();
    $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
    $suborderA = $order->sellerOrders->firstWhere('store_id', $storeA->id);
    $suborderB = $order->sellerOrders->firstWhere('store_id', $storeB->id);

    // Before payment confirmation, Seller A cannot advance suborder.
    $this->actingAs($sellerA)
        ->patch(route('seller.orders.status.update', $suborderA), ['status' => 'processing'])
        ->assertSessionHasErrors('status');

    // Verified PayMongo webhook marks parent + both suborders Paid.
    app(ProcessPayMongoWebhookAction::class)->execute([
        'data' => [
            'id' => 'evt_mv5_paid_001',
            'attributes' => [
                'type' => 'payment.paid',
                'data' => [
                    'id' => 'pay_mv5_001',
                    'attributes' => [
                        'source' => ['type' => 'gcash'],
                        'metadata' => [
                            'order_id' => (string) $order->id,
                            'payment_id' => (string) $payment->id,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($order->fresh()?->status)->toBe(OrderStatus::Paid)
        ->and($suborderA->fresh()?->status)->toBe(OrderStatus::Paid)
        ->and($suborderB->fresh()?->status)->toBe(OrderStatus::Paid);

    // Cross-seller IDOR and customer isolation checks.
    $this->actingAs($sellerB)->get(route('seller.orders.show', $suborderA))->assertForbidden();
    $this->actingAs($sellerB)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'processing'])->assertForbidden();
    $this->actingAs($customer)->get(route('seller.orders.show', $suborderA))->assertForbidden();

    // Seller A cannot cancel or refund post-payment suborder per Q4.
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'cancelled'])->assertSessionHasErrors('status');
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'refunded'])->assertSessionHasErrors('status');

    // Seller A sees only their own suborder & items on Seller Portal.
    $this->actingAs($sellerA)
        ->get(route('seller.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/orders/Index')
            ->has('sellerOrders.data', 1)
            ->where('sellerOrders.data.0.seller_order_number', $suborderA->seller_order_number));

    $this->actingAs($sellerA)
        ->get(route('seller.orders.show', $suborderA))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/orders/Show')
            ->where('sellerOrder.seller_order_number', $suborderA->seller_order_number)
            ->has('sellerOrder.items', 1)
            ->where('sellerOrder.items.0.product_name', 'Alpha Cold Brew carafe'));

    // Independent progression: Seller A advances Paid -> Processing -> Shipped -> Delivered while Seller B stays Paid.
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'processing'])->assertRedirect();
    expect($order->fresh()?->status)->toBe(OrderStatus::Processing);

    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), [
        'status' => 'shipped',
        'shipping_carrier' => 'LBC Express',
        'tracking_number' => 'LBC-ALPHA-881',
    ])->assertRedirect();

    expect($suborderA->fresh()?->shipping_carrier)->toBe('LBC Express')
        ->and($suborderA->fresh()?->tracking_number)->toBe('LBC-ALPHA-881')
        ->and($order->fresh()?->status)->toBe(OrderStatus::Processing);

    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'delivered'])->assertRedirect();
    expect($suborderA->fresh()?->status)->toBe(OrderStatus::Delivered)
        ->and($order->fresh()?->status)->toBe(OrderStatus::Processing)
        ->and(StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::Fulfilled->value)->where('reference', $suborderA->seller_order_number)->count())->toBe(1);

    // Customer portal order view shows store-level suborder status & independent tracking cleanly.
    $this->actingAs($customer)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/Show')
            ->has('order.seller_orders', 2)
            ->where('order.seller_orders.0.status', OrderStatus::Delivered->value)
            ->where('order.seller_orders.0.tracking_number', 'LBC-ALPHA-881')
            ->where('order.seller_orders.1.status', OrderStatus::Paid->value));

    // Seller B advances Paid -> Processing -> Shipped -> parent automatically rolls up to Shipped, then Delivered.
    $this->actingAs($sellerB)->patch(route('seller.orders.status.update', $suborderB), ['status' => 'processing'])->assertRedirect();
    $this->actingAs($sellerB)->patch(route('seller.orders.status.update', $suborderB), [
        'status' => 'shipped',
        'shipping_carrier' => 'J&T Express',
        'tracking_number' => 'JT-BETA-992',
    ])->assertRedirect();

    expect($order->fresh()?->status)->toBe(OrderStatus::Shipped);

    $this->actingAs($sellerB)->patch(route('seller.orders.status.update', $suborderB), ['status' => 'delivered'])->assertRedirect();

    expect($order->fresh()?->status)->toBe(OrderStatus::Delivered)
        ->and(StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::Fulfilled->value)->where('reference', $suborderB->seller_order_number)->count())->toBe(1)
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::SellerOrderStatusUpdated->value)->count())->toBe(6);

    Notification::assertSentTo($customer, OrderStatusUpdatedNotification::class);
});

test('admin governs post-payment seller suborder cancellation and refunds per suborder cleanly without corrupting sibling suborders', function (): void {
    $category = Category::factory()->create(['is_active' => true]);
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);
    $staffOrdersOnly = User::factory()->staff([StaffPermission::OrdersManage->value])->create();
    $superAdmin = User::factory()->admin()->create();

    ['user' => $sellerA, 'store' => $storeA] = createMv5SellerWorkspace('Studio A', 'studio-a');
    ['store' => $storeB] = createMv5SellerWorkspace('Studio B', 'studio-b');

    $productA = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeA->id,
        'seller_name' => $storeA->name,
        'base_price' => '2000.00',
        'stock_quantity' => 5,
        'status' => ProductStatus::Active,
    ]);

    $productB = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeB->id,
        'seller_name' => $storeB->name,
        'base_price' => '1000.00',
        'stock_quantity' => 7,
        'status' => ProductStatus::Active,
    ]);

    $this->actingAs($customer)->post(route('cart.items.store'), ['product_id' => $productA->id, 'quantity' => 1]);
    $this->actingAs($customer)->post(route('cart.items.store'), ['product_id' => $productB->id, 'quantity' => 2]);
    $this->actingAs($customer)->post(route('checkout.store'), [
        'address_id' => $address->id,
        'idempotency_key' => 'mv5-admin-suborder-gov-01',
    ])->assertRedirect();

    $order = Order::query()->with('sellerOrders')->firstOrFail();
    $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

    app(ProcessPayMongoWebhookAction::class)->execute([
        'data' => [
            'id' => 'evt_mv5_gov_001',
            'attributes' => [
                'type' => 'payment.paid',
                'data' => [
                    'id' => 'pay_mv5_gov_001',
                    'attributes' => [
                        'metadata' => [
                            'order_id' => (string) $order->id,
                            'payment_id' => (string) $payment->id,
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $suborderA = $order->sellerOrders->firstWhere('store_id', $storeA->id);
    $suborderB = $order->sellerOrders->firstWhere('store_id', $storeB->id);

    // Seller A fulfills suborder A all the way to Delivered.
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'processing'])->assertRedirect();
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'shipped'])->assertRedirect();
    $this->actingAs($sellerA)->patch(route('seller.orders.status.update', $suborderA), ['status' => 'delivered'])->assertRedirect();

    // Staff (orders.manage only, no catalog.manage) transitions Studio B to Processing, cannot refund (403), and cancels Studio B -> only productB stock releases.
    $this->actingAs($staffOrdersOnly)
        ->patch(route('admin.orders.seller-orders.status.update', [$order, $suborderB]), [
            'status' => 'processing',
        ])
        ->assertRedirect();

    $this->actingAs($staffOrdersOnly)
        ->patch(route('admin.orders.seller-orders.status.update', [$order, $suborderB]), [
            'status' => 'refunded',
        ])
        ->assertForbidden();

    $this->actingAs($staffOrdersOnly)
        ->patch(route('admin.orders.seller-orders.status.update', [$order, $suborderB]), [
            'status' => 'cancelled',
            'reason' => 'Stock damaged prior to dispatch',
        ])
        ->assertRedirect();

    expect($suborderA->fresh()?->status)->toBe(OrderStatus::Delivered)
        ->and($suborderB->fresh()?->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()?->status)->toBe(OrderStatus::Delivered)
        ->and($productA->fresh()?->stock_quantity)->toBe(4)
        ->and($productB->fresh()?->stock_quantity)->toBe(7);

    // Super Admin views admin order detail with seller_orders and refunds Studio A suborder -> parent rolls up to Refunded.
    $this->actingAs($superAdmin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/orders/Show')
            ->has('order.seller_orders', 2));

    $this->actingAs($superAdmin)
        ->patch(route('admin.orders.seller-orders.status.update', [$order, $suborderA]), [
            'status' => 'refunded',
            'reason' => 'Buyer approved post-delivery refund.',
        ])
        ->assertRedirect();

    expect($suborderA->fresh()?->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()?->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()?->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::AdminSellerOrderStatusUpdated->value)->count())->toBe(3);
});
