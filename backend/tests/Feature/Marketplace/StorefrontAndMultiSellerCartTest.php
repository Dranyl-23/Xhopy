<?php

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\Store;
use App\Domain\Seller\Models\StoreMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Create a store (optionally official or with seller profile) cleanly for MV-4 storefront tests.
 */
function createStorefrontTestStore(
    string $name,
    string $slug,
    StoreStatus $status = StoreStatus::Active,
    bool $isOfficial = false,
): Store {
    $owner = $isOfficial
        ? User::factory()->admin()->create()
        : User::factory()->seller()->create();

    $store = Store::query()->updateOrCreate(
        ['slug' => $slug],
        [
            'owner_user_id' => $owner->id,
            'name' => $name,
            'description' => "Curated catalog curated by {$name}.",
            'contact_email' => "{$slug}@xhopy.example",
            'contact_phone' => '+63 917 555 0100',
            'status' => $status,
            'is_official' => $isOfficial,
        ],
    );

    if (! $isOfficial) {
        StoreMembership::query()->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        SellerProfile::query()->create([
            'user_id' => $owner->id,
            'store_id' => $store->id,
            'business_name' => "{$name} Corp",
            'business_type' => 'corporation',
            'contact_phone' => '+63 917 555 0100',
            'business_address' => '100 Ayala Ave, Makati City',
            'status' => match ($status) {
                StoreStatus::Active => SellerStatus::Approved,
                StoreStatus::Pending => SellerStatus::Pending,
                StoreStatus::Suspended => SellerStatus::Suspended,
            },
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => $status === StoreStatus::Pending ? null : now()->subDay(),
            'approved_at' => $status === StoreStatus::Active ? now()->subDay() : null,
        ]);
    }

    return $store;
}

test('public stores directory lists only active official and seller stores and excludes non-active stores', function (): void {
    $category = Category::factory()->create(['is_active' => true]);

    $officialStore = createStorefrontTestStore('XHopy Official', 'xhopy-official', StoreStatus::Active, true);
    $activeSellerStore = createStorefrontTestStore('Manila Roast Coffee', 'manila-roast-coffee', StoreStatus::Active);
    $pendingStore = createStorefrontTestStore('Pending Tea House', 'pending-tea-house', StoreStatus::Pending);
    $suspendedStore = createStorefrontTestStore('Suspended Tech Shop', 'suspended-tech-shop', StoreStatus::Suspended);

    Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $officialStore->id,
        'seller_name' => $officialStore->name,
        'status' => ProductStatus::Active,
        'stock_quantity' => 10,
    ]);

    Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $activeSellerStore->id,
        'seller_name' => $activeSellerStore->name,
        'status' => ProductStatus::Active,
        'stock_quantity' => 5,
    ]);

    Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $pendingStore->id,
        'seller_name' => $pendingStore->name,
        'status' => ProductStatus::Active,
        'stock_quantity' => 5,
    ]);

    Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $suspendedStore->id,
        'seller_name' => $suspendedStore->name,
        'status' => ProductStatus::Active,
        'stock_quantity' => 5,
    ]);

    $this->get(route('stores.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stores/Index')
            ->has('stores', 2)
            ->where('stores.0.slug', 'xhopy-official')
            ->where('stores.0.isOfficial', true)
            ->where('stores.0.productCount', 1)
            ->where('stores.1.slug', 'manila-roast-coffee')
            ->where('stores.1.isOfficial', false)
            ->where('stores.1.productCount', 1));

    $this->get(route('stores.index', ['q' => 'Manila']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stores/Index')
            ->has('stores', 1)
            ->where('stores.0.slug', 'manila-roast-coffee'));
});

test('public store page shows only that active store products and returns 404 for pending or suspended stores', function (): void {
    $category = Category::factory()->create(['is_active' => true]);
    $brand = Brand::factory()->create(['is_active' => true]);

    $storeA = createStorefrontTestStore('Alpha Acoustics', 'alpha-acoustics', StoreStatus::Active);
    $storeB = createStorefrontTestStore('Beta Gadgets', 'beta-gadgets', StoreStatus::Active);
    $suspendedStore = createStorefrontTestStore('Suspended Vault', 'suspended-vault', StoreStatus::Suspended);
    $pendingStore = createStorefrontTestStore('Pending Vault', 'pending-vault', StoreStatus::Pending);

    $alphaActive = Product::factory()->create([
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'store_id' => $storeA->id,
        'seller_name' => $storeA->name,
        'name' => 'Alpha Studio Monitor',
        'slug' => 'alpha-studio-monitor',
        'status' => ProductStatus::Active,
        'stock_quantity' => 6,
    ]);

    Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeA->id,
        'seller_name' => $storeA->name,
        'name' => 'Alpha Pending Draft',
        'slug' => 'alpha-pending-draft',
        'status' => ProductStatus::PendingReview,
        'stock_quantity' => 6,
    ]);

    Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeB->id,
        'seller_name' => $storeB->name,
        'name' => 'Beta Headphones',
        'slug' => 'beta-headphones',
        'status' => ProductStatus::Active,
        'stock_quantity' => 6,
    ]);

    $this->get(route('stores.show', $storeA->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stores/Show')
            ->where('store.slug', 'alpha-acoustics')
            ->where('store.url', route('stores.show', ['store' => 'alpha-acoustics']))
            ->has('products', 1)
            ->where('products.0.id', $alphaActive->id)
            ->where('products.0.storeUrl', route('stores.show', ['store' => 'alpha-acoustics'])));

    $this->get(route('stores.show', $suspendedStore->slug))->assertNotFound();
    $this->get(route('stores.show', $pendingStore->slug))->assertNotFound();
    $this->get('/stores/does-not-exist')->assertNotFound();
});

test('catalog store filter and product detail include verified store attribution', function (): void {
    $category = Category::factory()->create(['is_active' => true]);
    $officialStore = createStorefrontTestStore('XHopy Official', 'xhopy-official', StoreStatus::Active, true);
    $sellerStore = createStorefrontTestStore('Cebu Craft Co', 'cebu-craft-co', StoreStatus::Active);

    $officialProduct = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $officialStore->id,
        'seller_name' => $officialStore->name,
        'name' => 'XHopy Mechanical Keycap Set',
        'slug' => 'xhopy-mechanical-keycap-set',
        'status' => ProductStatus::Active,
        'stock_quantity' => 12,
    ]);

    Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $sellerStore->id,
        'seller_name' => $sellerStore->name,
        'name' => 'Handwoven Desk Organizer',
        'slug' => 'handwoven-desk-organizer',
        'status' => ProductStatus::Active,
        'stock_quantity' => 9,
    ]);

    $this->get(route('catalog.index', ['store' => 'cebu-craft-co']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Index')
            ->where('filters.store', 'cebu-craft-co')
            ->has('stores', 2)
            ->has('products', 1)
            ->where('products.0.storeSlug', 'cebu-craft-co')
            ->where('products.0.isOfficialStore', false)
            ->where('products.0.storeUrl', route('stores.show', ['store' => 'cebu-craft-co'])));

    $this->get(route('products.show', $officialProduct->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Show')
            ->where('product.storeSlug', 'xhopy-official')
            ->where('product.isOfficialStore', true)
            ->where('product.sellerName', 'XHopy Official')
            ->where('product.storeUrl', route('stores.show', ['store' => 'xhopy-official'])));
});

test('multi-seller cart groups line items by store, computes accurate store subtotals, and flags suspended store items cleanly', function (): void {
    $category = Category::factory()->create(['is_active' => true]);
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->defaultShipping()->create(['user_id' => $customer->id]);

    $officialStore = createStorefrontTestStore('XHopy Official', 'xhopy-official', StoreStatus::Active, true);
    $sellerStore = createStorefrontTestStore('Manila Roast Coffee', 'manila-roast-coffee', StoreStatus::Active);

    $officialKeyboard = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $officialStore->id,
        'seller_name' => $officialStore->name,
        'name' => 'XHopy Tactile Keyboard',
        'base_price' => '3200.00',
        'status' => ProductStatus::Active,
        'stock_quantity' => 10,
    ]);

    $coffeeBeans = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $sellerStore->id,
        'seller_name' => $sellerStore->name,
        'name' => 'Sagada Whole Beans',
        'base_price' => '650.00',
        'status' => ProductStatus::Active,
        'stock_quantity' => 10,
    ]);

    $cartService = app(CartService::class);
    $cartService->addItem($customer, null, $officialKeyboard->id, null, 1);
    $cart = $cartService->addItem($customer, null, $coffeeBeans->id, null, 2);

    $presented = $cartService->presentCart($cart);

    expect($presented['itemCount'])->toBe(3)
        ->and($presented['storeCount'])->toBe(2)
        ->and($presented['subtotalCentavos'])->toBe(450000)
        ->and($presented['subtotalFormatted'])->toBe('₱4,500.00')
        ->and($presented['hasStockIssues'])->toBeFalse()
        ->and($presented['sellerGroups'])->toHaveCount(2);

    $this->actingAs($customer)
        ->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cart/Index')
            ->where('cart.storeCount', 2)
            ->where('cart.subtotalFormatted', '₱4,500.00')
            ->has('cart.sellerGroups', 2));

    $this->actingAs($customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('checkout/Index')
            ->where('cart.storeCount', 2)
            ->has('cart.sellerGroups', 2));

    // Suspending Seller B leaves Seller A valid while flagging only Seller B's cart item unavailable & blocking checkout.
    $sellerStore->forceFill(['status' => StoreStatus::Suspended])->save();

    $reloadedCart = $cartService->presentCart($cart->fresh());
    $suspendedGroup = collect($reloadedCart['sellerGroups'])
        ->firstWhere('storeName', 'Manila Roast Coffee');
    $officialGroup = collect($reloadedCart['sellerGroups'])
        ->firstWhere('storeName', 'XHopy Official');

    expect($reloadedCart['hasStockIssues'])->toBeTrue()
        ->and($officialGroup['hasStockIssues'])->toBeFalse()
        ->and($suspendedGroup['hasStockIssues'])->toBeTrue()
        ->and($suspendedGroup['items'][0]['isAvailable'])->toBeFalse();

    $this->actingAs($customer)
        ->post(route('checkout.store'), [
            'address_id' => $address->id,
            'idempotency_key' => 'mv4-suspended-store-key',
        ])
        ->assertSessionHasErrors(['cart']);
});
