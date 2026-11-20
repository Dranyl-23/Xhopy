<?php

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Notifications\OrderStatusUpdatedNotification;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createBatch6Order(User $customer, Product $product, OrderStatus $status = OrderStatus::Paid, int $qty = 2): Order
{
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);

    $order = Order::query()->create([
        'order_number' => 'XHP-B6-'.strtoupper(Str::random(6)),
        'user_id' => $customer->id,
        'customer_address_id' => $address->id,
        'idempotency_key' => (string) Str::uuid(),
        'status' => $status,
        'payment_status' => $status === OrderStatus::PendingPayment ? PaymentStatus::Pending : PaymentStatus::Paid,
        'currency' => 'PHP',
        'subtotal_amount' => number_format((float) $product->base_price * $qty, 2, '.', ''),
        'shipping_amount' => '0.00',
        'discount_amount' => '0.00',
        'tax_amount' => '0.00',
        'total_amount' => number_format((float) $product->base_price * $qty, 2, '.', ''),
        'shipping_recipient_name' => $address->recipient_name,
        'shipping_phone' => $address->phone,
        'shipping_country_code' => $address->country_code,
        'shipping_province' => $address->province,
        'shipping_city' => $address->city,
        'shipping_barangay' => $address->barangay,
        'shipping_postal_code' => $address->postal_code,
        'shipping_address_line_1' => $address->address_line_1,
        'placed_at' => now(),
        'paid_at' => $status === OrderStatus::PendingPayment ? null : now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => null,
        'seller_name' => $product->seller_name,
        'product_name' => $product->name,
        'variant_title' => null,
        'sku' => $product->sku,
        'currency' => 'PHP',
        'unit_price' => $product->base_price,
        'quantity' => $qty,
        'line_total' => number_format((float) $product->base_price * $qty, 2, '.', ''),
    ]);

    return $order;
}

test('guests and customers are denied access to operations, orders admin, and inventory endpoints while MFA middleware enforces privileged protection when enabled', function () {
    $customer = User::factory()->create();
    $staff = User::factory()->staff()->create();

    $this->get(route('admin.operations.index'))->assertRedirect(route('login'));
    $this->actingAs($customer)->get(route('admin.operations.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.inventory.index'))->assertForbidden();
    $this->actingAs($customer)->post(route('admin.inventory.adjust'), [])->assertForbidden();

    config(['fortify.require_mfa_for_privileged' => true]);

    $this->actingAs($staff)->get(route('admin.operations.index'))->assertRedirect(route('security.edit'));
    $this->actingAs($staff)->post(route('admin.inventory.adjust'), [])->assertForbidden();
});

test('staff can fulfill paid orders with shipping tracking and customer receives mail and database notifications while refunds require admin role', function () {
    Notification::fake();

    $staff = User::factory()->staff()->create();
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Studio Ergonomic Chair',
        'sku' => 'B6-CHAIR-01',
        'base_price' => '4500.00',
        'stock_quantity' => 8,
        'status' => ProductStatus::Active,
    ]);

    $order = createBatch6Order($customer, $product, OrderStatus::Paid, 1);

    // Invalid jump Paid -> Delivered is rejected cleanly
    $this->actingAs($staff)
        ->patch(route('admin.orders.status.update', ['order' => $order->id]), [
            'status' => 'delivered',
        ])
        ->assertSessionHasErrors('status');

    // Valid transition Paid -> Processing
    $this->actingAs($staff)
        ->patch(route('admin.orders.status.update', ['order' => $order->id]), [
            'status' => 'processing',
            'reason' => 'Packed at Manila warehouse',
        ])
        ->assertRedirect();

    expect($order->fresh()?->status)->toBe(OrderStatus::Processing);

    // Staff cannot issue refunds (least privilege -> Admin only)
    $this->actingAs($staff)
        ->patch(route('admin.orders.status.update', ['order' => $order->id]), [
            'status' => 'refunded',
        ])
        ->assertForbidden();

    // Staff transitions Processing -> Shipped with carrier and tracking
    $this->actingAs($staff)
        ->patch(route('admin.orders.status.update', ['order' => $order->id]), [
            'status' => 'shipped',
            'shipping_carrier' => 'LBC Express',
            'tracking_number' => 'LBC-2026-88412',
        ])
        ->assertRedirect();

    // Staff transitions Shipped -> Delivered -> logs Fulfilled ledger entry (zero delta)
    $this->actingAs($staff)
        ->patch(route('admin.orders.status.update', ['order' => $order->id]), [
            'status' => 'delivered',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->shipping_carrier)->toBe('LBC Express')
        ->and($order->tracking_number)->toBe('LBC-2026-88412')
        ->and($order->shipped_at)->not->toBeNull()
        ->and($order->delivered_at)->not->toBeNull()
        ->and($product->fresh()?->stock_quantity)->toBe(8)
        ->and(StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::Fulfilled)->count())->toBe(1)
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::OrderStatusUpdated->value)->count())->toBe(3);

    Notification::assertSentToTimes($customer, OrderStatusUpdatedNotification::class, 3);

    // Admin can issue Refunded transition cleanly
    $this->actingAs($admin)
        ->patch(route('admin.orders.status.update', ['order' => $order->id]), [
            'status' => 'refunded',
            'reason' => 'Customer goodwill return approved',
        ])
        ->assertRedirect();

    expect($order->fresh()?->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()?->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()?->refunded_at)->not->toBeNull();
});

test('cancelling a processing order releases reserved stock back into catalog and logs release movement', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'base_price' => '1500.00',
        'stock_quantity' => 4,
        'status' => ProductStatus::Active,
    ]);

    $order = createBatch6Order($customer, $product, OrderStatus::Processing, 2);

    $this->actingAs($admin)
        ->patch(route('admin.orders.status.update', ['order' => $order->id]), [
            'status' => 'cancelled',
            'reason' => 'Buyer requested cancellation prior to courier handoff',
        ])
        ->assertRedirect();

    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()?->stock_quantity)->toBe(6)
        ->and(StockMovement::query()->where('order_id', $order->id)->where('type', StockMovementType::Released)->count())->toBe(1);
});

test('bulk order transition safely updates eligible paid orders and skips ineligible statuses', function () {
    $staff = User::factory()->staff()->create();
    $customer = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'base_price' => '800.00',
        'stock_quantity' => 20,
        'status' => ProductStatus::Active,
    ]);

    $paidOrder = createBatch6Order($customer, $product, OrderStatus::Paid, 1);
    $pendingOrder = createBatch6Order($customer, $product, OrderStatus::PendingPayment, 1);

    $this->actingAs($staff)
        ->post(route('admin.orders.bulk-transition'), [
            'order_ids' => [$paidOrder->id, $pendingOrder->id],
            'status' => 'processing',
        ])
        ->assertRedirect();

    expect($paidOrder->fresh()?->status)->toBe(OrderStatus::Processing)
        ->and($pendingOrder->fresh()?->status)->toBe(OrderStatus::PendingPayment)
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::OrdersBulkTransitioned->value)->count())->toBe(1);
});

test('inventory adjustment and csv import validate inputs strictly, reject formula injection, and rollback atomically on error', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['is_active' => true]);

    $singleProduct = Product::factory()->create([
        'category_id' => $category->id,
        'sku' => 'B6-LAMP-01',
        'stock_quantity' => 5,
        'status' => ProductStatus::Active,
    ]);

    $variantParent = Product::factory()->create([
        'category_id' => $category->id,
        'sku' => 'B6-PAD-01',
        'stock_quantity' => 10,
        'status' => ProductStatus::Active,
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $variantParent->id,
        'sku' => 'B6-PAD-BLK',
        'stock_quantity' => 10,
        'is_default' => true,
        'is_active' => true,
    ]);

    // Direct adjustment on variant updates variant + syncs parent total
    $this->actingAs($admin)
        ->post(route('admin.inventory.adjust'), [
            'product_id' => $variantParent->id,
            'product_variant_id' => $variant->id,
            'stock_quantity' => 18,
            'reference' => 'Warehouse cycle restock',
        ])
        ->assertRedirect();

    expect($variant->fresh()?->stock_quantity)->toBe(18)
        ->and($variantParent->fresh()?->stock_quantity)->toBe(18)
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::InventoryAdjusted->value)->count())->toBe(1);

    // Unsafe CSV with spreadsheet formula prefix on row 2 rolls back row 1 completely
    $unsafeCsvContent = "sku,stock_quantity,reason\nB6-LAMP-01,99,Valid restock\nB6-PAD-BLK,30,=CMD()";
    $unsafeFile = UploadedFile::fake()->createWithContent('unsafe.csv', $unsafeCsvContent);

    $this->actingAs($admin)
        ->post(route('admin.inventory.import'), [
            'csv_file' => $unsafeFile,
        ])
        ->assertSessionHasErrors('csv_file');

    expect($singleProduct->fresh()?->stock_quantity)->toBe(5)
        ->and($variant->fresh()?->stock_quantity)->toBe(18);

    // Valid CSV commits both rows atomically
    $validCsvContent = "sku,stock_quantity,reason\nB6-LAMP-01,22,Restock batch 1\nB6-PAD-BLK,14,Restock batch 2";
    $validFile = UploadedFile::fake()->createWithContent('valid.csv', $validCsvContent);

    $this->actingAs($admin)
        ->post(route('admin.inventory.import'), [
            'csv_file' => $validFile,
        ])
        ->assertRedirect();

    expect($singleProduct->fresh()?->stock_quantity)->toBe(22)
        ->and($variant->fresh()?->stock_quantity)->toBe(14)
        ->and($variantParent->fresh()?->stock_quantity)->toBe(14)
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::InventoryBulkImported->value)->count())->toBe(1);
});

test('operations dashboard computes real analytics and customers see persistent database order notifications', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);

    $product = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Analytics Monitor Stand',
        'sku' => 'B6-STAND-01',
        'base_price' => '2100.00',
        'stock_quantity' => 3,
        'status' => ProductStatus::Active,
    ]);

    $order = createBatch6Order($customer, $product, OrderStatus::Paid, 2);

    // Transition so customer receives persistent DB notification
    $this->actingAs($admin)
        ->patch(route('admin.orders.status.update', ['order' => $order->id]), [
            'status' => 'processing',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.operations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Operations')
            ->where('analytics.kpis.grossRevenueFormatted', '₱4,200.00')
            ->where('analytics.kpis.confirmedOrdersCount', 1)
            ->where('analytics.kpis.fulfillmentQueueCount', 1)
            ->where('analytics.kpis.lowStockCount', 1)
            ->has('analytics.topSellingProducts', 1)
            ->has('analytics.dailyTrend', 7)
        );

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Users')
            ->where('users.data.1.orders_count', 1)
            ->where('users.data.1.total_spent_formatted', '₱4,200.00')
        );

    // Customer sees unread notification and can mark all read
    $this->actingAs($customer)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotificationsCount', 1)
            ->has('notifications', 1)
        );

    $this->actingAs($customer)
        ->post(route('orders.notifications.read'))
        ->assertRedirect();

    expect($customer->unreadNotifications()->count())->toBe(0);
});
