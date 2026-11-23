<?php

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Notifications\ProductModerationNotification;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\Store;
use App\Domain\Seller\Models\StoreMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, profile: SellerProfile, store: Store}
 */
function createApprovedSellerWorkspace(string $storeName, string $slug): array
{
    $user = User::factory()->seller()->create();
    $store = Store::query()->create([
        'owner_user_id' => $user->id,
        'name' => $storeName,
        'slug' => $slug,
        'description' => 'Verified marketplace seller storefront.',
        'contact_email' => "{$slug}@xhopy.example",
        'contact_phone' => '+63 917 555 0100',
        'status' => StoreStatus::Active,
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
        'business_name' => "{$storeName} Corp",
        'business_type' => 'sole_proprietor',
        'contact_phone' => '+63 917 555 0100',
        'business_address' => '100 Ayala Ave, Makati City',
        'status' => SellerStatus::Approved,
        'submitted_at' => now()->subDays(2),
        'reviewed_at' => now()->subDay(),
        'approved_at' => now()->subDay(),
    ]);

    return compact('user', 'profile', 'store');
}

test('unapproved sellers and customers are blocked from seller store, product, and inventory operations', function (): void {
    $customer = User::factory()->create();
    $pendingSeller = User::factory()->seller()->create();

    $pendingStore = Store::query()->create([
        'owner_user_id' => $pendingSeller->id,
        'name' => 'Pending Craft Co',
        'slug' => 'pending-craft-co',
        'status' => StoreStatus::Pending,
        'is_official' => false,
    ]);

    SellerProfile::query()->create([
        'user_id' => $pendingSeller->id,
        'store_id' => $pendingStore->id,
        'business_name' => 'Pending Craft Co',
        'business_type' => 'sole_proprietor',
        'contact_phone' => '+63 917 000 0001',
        'business_address' => 'Quezon City',
        'status' => SellerStatus::Pending,
        'submitted_at' => now(),
    ]);

    $this->actingAs($customer)->get(route('seller.products.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('seller.inventory.index'))->assertForbidden();
    $this->actingAs($customer)->patch(route('seller.store.update'), [])->assertForbidden();

    $this->actingAs($pendingSeller)->get(route('seller.products.index'))->assertRedirect(route('seller.onboarding'));
    $this->actingAs($pendingSeller)->get(route('seller.inventory.index'))->assertRedirect(route('seller.onboarding'));
    $this->actingAs($pendingSeller)->post(route('seller.products.store'), [])->assertForbidden();
    $this->actingAs($pendingSeller)->patch(route('seller.store.update'), ['name' => 'Blocked'])->assertForbidden();
    $this->actingAs($pendingSeller)->post(route('seller.inventory.adjust'), [])->assertForbidden();
});

test('approved seller updates store profile, syncs product attribution, and submits listings through mandatory admin moderation', function (): void {
    Notification::fake();

    $category = Category::factory()->create(['is_active' => true]);
    ['user' => $sellerA, 'store' => $storeA] = createApprovedSellerWorkspace('Makati Audio Lab', 'makati-audio-lab');
    ['store' => $storeB] = createApprovedSellerWorkspace('Cebu Desk Gear', 'cebu-desk-gear');
    $admin = User::factory()->admin()->create();

    $otherStoreProduct = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeB->id,
        'seller_name' => $storeB->name,
        'status' => ProductStatus::Active,
    ]);

    // Update store profile cleanly without trusting status/is_official overrides.
    $this->actingAs($sellerA)
        ->patch(route('seller.store.update'), [
            'name' => 'Makati Audio Studio',
            'description' => 'Precision studio monitors and USB DACs.',
            'contact_email' => 'hello@makatiaudio.ph',
            'contact_phone' => '+63 917 555 2233',
            'is_official' => true,
            'status' => 'suspended',
        ])
        ->assertRedirect(route('seller.store.edit'));

    $storeA->refresh();
    expect($storeA->name)->toBe('Makati Audio Studio')
        ->and($storeA->slug)->toBe('makati-audio-studio')
        ->and($storeA->is_official)->toBeFalse()
        ->and($storeA->status)->toBe(StoreStatus::Active)
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::SellerStoreUpdated->value)->exists())->toBeTrue();

    // Seller cannot directly self-publish status=active.
    $this->actingAs($sellerA)
        ->post(route('seller.products.store'), [
            'category_id' => $category->id,
            'name' => 'Illegal Self Publish Mic',
            'sku' => 'MAL-SELF-001',
            'status' => ProductStatus::Active->value,
            'currency' => 'PHP',
            'base_price' => '2499.00',
            'stock_quantity' => 10,
        ])
        ->assertSessionHasErrors(['status']);

    // Seller submits listing for review; store_id and is_featured tampering are overridden server-side.
    $this->actingAs($sellerA)
        ->post(route('seller.products.store'), [
            'category_id' => $category->id,
            'store_id' => $storeB->id,
            'is_featured' => true,
            'name' => 'Makati Studio Condenser Mic',
            'sku' => 'MAL-MIC-900',
            'status' => ProductStatus::PendingReview->value,
            'currency' => 'PHP',
            'base_price' => '3499.00',
            'stock_quantity' => 12,
        ])
        ->assertRedirect();

    $product = Product::query()->where('sku', 'MAL-MIC-900')->firstOrFail();
    expect($product->store_id)->toBe($storeA->id)
        ->and($product->seller_name)->toBe('Makati Audio Studio')
        ->and($product->is_featured)->toBeFalse()
        ->and($product->status)->toBe(ProductStatus::PendingReview)
        ->and($product->submitted_for_review_at)->not->toBeNull();

    // Store name update also propagates to existing store products cleanly without touching other stores.
    $this->actingAs($sellerA)
        ->patch(route('seller.store.update'), [
            'name' => 'Makati Pro Audio',
            'description' => 'Updated store bio',
        ])
        ->assertRedirect(route('seller.store.edit'));

    expect($product->fresh()->seller_name)->toBe('Makati Pro Audio')
        ->and($otherStoreProduct->fresh()->seller_name)->toBe('Cebu Desk Gear');

    // PendingReview listing is NOT exposed on public storefront until Admin approves.
    $this->get(route('catalog.index', ['q' => 'Makati Studio Condenser']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 0));
    $this->get(route('products.show', $product->slug))->assertNotFound();

    // Admin rejection requires feedback reason.
    $this->actingAs($admin)
        ->patch(route('admin.catalog.products.moderate', $product), [
            'status' => ProductStatus::Rejected->value,
            'reason' => '',
        ])
        ->assertSessionHasErrors(['reason']);

    // Admin requests changes with reason -> notification + audit sent.
    $this->actingAs($admin)
        ->patch(route('admin.catalog.products.moderate', $product), [
            'status' => ProductStatus::Rejected->value,
            'reason' => 'Add polar pattern spec before publishing.',
        ])
        ->assertRedirect(route('admin.catalog.products.edit', $product));

    $product->refresh();
    expect($product->status)->toBe(ProductStatus::Rejected)
        ->and($product->moderation_notes)->toBe('Add polar pattern spec before publishing.')
        ->and($product->moderated_by)->toBe($admin->id);

    Notification::assertSentTo($sellerA, ProductModerationNotification::class);

    // Seller resubmits with updates and Admin approves live -> appears on storefront.
    $this->actingAs($sellerA)
        ->patch(route('seller.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Makati Studio Condenser Mic Mk II',
            'sku' => 'MAL-MIC-900',
            'short_description' => 'Cardioid condenser mic for vocal booths.',
            'status' => ProductStatus::PendingReview->value,
            'currency' => 'PHP',
            'base_price' => '3499.00',
            'stock_quantity' => 12,
        ])
        ->assertRedirect(route('seller.products.edit', $product));

    expect($product->fresh()->moderation_notes)->toBeNull()
        ->and($product->fresh()->status)->toBe(ProductStatus::PendingReview);

    $this->actingAs($admin)
        ->patch(route('admin.catalog.products.moderate', $product), [
            'status' => ProductStatus::Active->value,
        ])
        ->assertRedirect(route('admin.catalog.products.edit', $product));

    $product->refresh();
    expect($product->status)->toBe(ProductStatus::Active)
        ->and($product->published_at)->not->toBeNull()
        ->and(SecurityAuditLog::query()->where('event', SecurityAuditEvent::AdminProductApproved->value)->exists())->toBeTrue();

    $this->get(route('products.show', $product->slug))->assertOk();
});

test('cross-seller idor protections isolate products, private images, manual stock adjustments, and csv imports', function (): void {
    Storage::fake('local');

    $category = Category::factory()->create(['is_active' => true]);
    ['user' => $sellerA, 'store' => $storeA] = createApprovedSellerWorkspace('Seller Alpha', 'seller-alpha');
    ['user' => $sellerB, 'store' => $storeB] = createApprovedSellerWorkspace('Seller Beta', 'seller-beta');

    $alphaProduct = Product::factory()->draft()->create([
        'category_id' => $category->id,
        'store_id' => $storeA->id,
        'seller_name' => $storeA->name,
        'sku' => 'ALPHA-SKU-01',
        'stock_quantity' => 10,
    ]);

    $betaProduct = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $storeB->id,
        'seller_name' => $storeB->name,
        'sku' => 'BETA-SKU-01',
        'status' => ProductStatus::Active,
        'stock_quantity' => 20,
    ]);

    // Seller A index & inventory & CSV template expose only Store A products/SKUs.
    $this->actingAs($sellerA)
        ->get(route('seller.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/products/Index')
            ->has('products', 1)
            ->where('products.0.sku', 'ALPHA-SKU-01')
        );

    $templateResponse = $this->actingAs($sellerA)->get(route('seller.inventory.template'))->assertOk();
    expect($templateResponse->getContent())->toContain('ALPHA-SKU-01')
        ->not->toContain('BETA-SKU-01');

    // Seller A uploads image on their own draft product and can preview it privately, while Seller B cannot.
    $this->actingAs($sellerA)
        ->post(route('seller.products.images.store', $alphaProduct), [
            'image' => UploadedFile::fake()->image('alpha.png', 200, 200),
            'alt_text' => 'Alpha Studio Angle',
            'is_primary' => true,
        ])
        ->assertRedirect(route('seller.products.edit', $alphaProduct));

    $alphaImage = ProductImage::query()->where('product_id', $alphaProduct->id)->firstOrFail();
    $this->actingAs($sellerA)->get(route('catalog.media.show', $alphaImage))->assertOk();
    $this->actingAs($sellerB)->get(route('catalog.media.show', $alphaImage))->assertNotFound();

    // Seller B attempts cross-tenant product edit, update, delete, image write, and stock adjustment -> 403 Forbidden.
    $this->actingAs($sellerB)->get(route('seller.products.edit', $alphaProduct))->assertForbidden();
    $this->actingAs($sellerB)->patch(route('seller.products.update', $alphaProduct), [
        'category_id' => $category->id,
        'name' => 'Hijacked',
        'sku' => 'ALPHA-SKU-01',
        'status' => ProductStatus::Draft->value,
        'currency' => 'PHP',
        'base_price' => '100.00',
        'stock_quantity' => 1,
    ])->assertForbidden();
    $this->actingAs($sellerB)->delete(route('seller.products.destroy', $alphaProduct))->assertForbidden();
    $this->actingAs($sellerB)->post(route('seller.products.images.store', $alphaProduct), [
        'image' => UploadedFile::fake()->image('hack.png', 100, 100),
        'alt_text' => 'Hack',
    ])->assertForbidden();
    $this->actingAs($sellerB)->delete(route('seller.products.images.destroy', [$alphaProduct, $alphaImage]))->assertForbidden();

    $this->actingAs($sellerB)
        ->post(route('seller.inventory.adjust'), [
            'product_id' => $alphaProduct->id,
            'stock_quantity' => 0,
            'reference' => 'Cross tenant attack',
        ])
        ->assertForbidden();
    expect($alphaProduct->fresh()->stock_quantity)->toBe(10);

    // Mixed CSV import referencing Beta's SKU rolls back the entire import atomically.
    $badCsv = UploadedFile::fake()->createWithContent(
        'mixed.csv',
        "sku,stock_quantity,reason\nALPHA-SKU-01,77,Local restock\nBETA-SKU-01,0,Cross seller tamper\n",
    );

    $this->actingAs($sellerA)
        ->post(route('seller.inventory.import'), ['csv_file' => $badCsv])
        ->assertSessionHasErrors(['csv_file']);

    expect($alphaProduct->fresh()->stock_quantity)->toBe(10)
        ->and($betaProduct->fresh()->stock_quantity)->toBe(20);

    // Valid Seller A CSV import succeeds and stamps StockMovement.store_id cleanly.
    $goodCsv = UploadedFile::fake()->createWithContent(
        'alpha.csv',
        "sku,stock_quantity,reason\nALPHA-SKU-01,35,Cycle count confirmed\n",
    );

    $this->actingAs($sellerA)
        ->post(route('seller.inventory.import'), ['csv_file' => $goodCsv])
        ->assertSessionHasNoErrors();

    expect($alphaProduct->fresh()->stock_quantity)->toBe(35)
        ->and(StockMovement::query()->where('product_id', $alphaProduct->id)->latest('id')->first()?->store_id)->toBe($storeA->id);
});

test('suspending a seller store immediately hides active listings from storefront, wishlist, and cart checkout', function (): void {
    $category = Category::factory()->create(['is_active' => true]);
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    ['profile' => $profile, 'store' => $store] = createApprovedSellerWorkspace('Davao Gear Co', 'davao-gear-co');

    $product = Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $store->id,
        'seller_name' => $store->name,
        'status' => ProductStatus::Active,
        'stock_quantity' => 8,
    ]);

    $this->get(route('products.show', $product->slug))->assertOk();
    $this->actingAs($customer)->post(route('wishlist.toggle', $product))->assertRedirect();

    $cartService = app(CartService::class);
    $cart = $cartService->addItem($customer, null, $product->id, null, 1);
    expect($cartService->presentCart($cart)['hasStockIssues'])->toBeFalse();

    // Super Admin suspends seller -> store transitions to suspended and active products disappear everywhere.
    $this->actingAs($admin)
        ->patch(route('admin.sellers.status.update', $profile), [
            'status' => SellerStatus::Suspended->value,
            'reason' => 'Fulfillment compliance pause.',
        ])
        ->assertRedirect(route('admin.sellers.show', $profile));

    expect($store->fresh()->status)->toBe(StoreStatus::Suspended);

    $this->get(route('products.show', $product->slug))->assertNotFound();
    $this->get(route('catalog.index', ['q' => $product->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 0));

    $this->actingAs($customer)
        ->get(route('wishlist.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('items', 0));

    expect($cartService->presentCart($cart->fresh())['hasStockIssues'])->toBeTrue();

    expect(fn () => $cartService->addItem($customer, null, $product->id, null, 1))
        ->toThrow(ValidationException::class);
});
