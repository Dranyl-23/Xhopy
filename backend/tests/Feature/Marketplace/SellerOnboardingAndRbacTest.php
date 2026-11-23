<?php

use App\Domain\Catalog\Actions\SaveProductAction;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Models\WishlistItem;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Enums\StaffPermission;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Domain\Seller\Enums\SellerDocumentType;
use App\Domain\Seller\Enums\SellerStatus;
use App\Domain\Seller\Enums\StoreStatus;
use App\Domain\Seller\Models\SellerProfile;
use App\Domain\Seller\Models\SellerVerificationDocument;
use App\Domain\Seller\Models\Store;
use App\Domain\Seller\Models\StoreMembership;
use App\Domain\Seller\Notifications\SellerApplicationStatusNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function sellerOnboardingPayload(array $overrides = []): array
{
    return array_merge([
        'store_name' => 'Manila Sound Studio',
        'store_slug' => 'manila-sound-studio',
        'store_description' => 'Curated studio microphones and acoustic hardware.',
        'contact_email' => 'studio@manilasound.example',
        'contact_phone' => '+63 917 555 0142',
        'business_name' => 'Manila Sound Ventures OPC',
        'business_type' => 'sole_proprietorship',
        'business_address' => '120 Jupiter St, Bel-Air, Makati City',
        'tax_identifier' => 'TIN-402-991-002',
        'payout_notes' => 'Primary business bank settlement contact.',
    ], $overrides);
}

test('granular rbac isolates customer, legacy staff, seller-reviewer staff, and super admin routes', function (): void {
    $customer = User::factory()->create();
    $legacyStaff = User::factory()->staff()->create();
    $sellerReviewer = User::factory()->staff([StaffPermission::SellersReview->value])->create();
    $superAdmin = User::factory()->admin()->create();

    // Customers are blocked from every admin route.
    $this->actingAs($customer)->get(route('admin.operations.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.inventory.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.catalog.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.sellers.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.users.index'))->assertForbidden();

    // Legacy staff (staff_permissions = null) keeps catalog/orders/inventory access, but cannot review sellers or manage users.
    $this->actingAs($legacyStaff)->get(route('admin.operations.index'))->assertOk();
    $this->actingAs($legacyStaff)->get(route('admin.orders.index'))->assertOk();
    $this->actingAs($legacyStaff)->get(route('admin.inventory.index'))->assertOk();
    $this->actingAs($legacyStaff)->get(route('admin.catalog.index'))->assertOk();
    $this->actingAs($legacyStaff)->get(route('admin.sellers.index'))->assertForbidden();
    $this->actingAs($legacyStaff)->get(route('admin.users.index'))->assertForbidden();

    // Seller-reviewer-only staff accesses /admin/sellers only and is blocked from orders/inventory/catalog/operations/users.
    $this->actingAs($sellerReviewer)->get(route('admin.sellers.index'))->assertOk();
    $this->actingAs($sellerReviewer)->get(route('admin.operations.index'))->assertForbidden();
    $this->actingAs($sellerReviewer)->get(route('admin.orders.index'))->assertForbidden();
    $this->actingAs($sellerReviewer)->get(route('admin.inventory.index'))->assertForbidden();
    $this->actingAs($sellerReviewer)->get(route('admin.catalog.index'))->assertForbidden();
    $this->actingAs($sellerReviewer)->get(route('admin.users.index'))->assertForbidden();

    // Super Admin accesses everything including /admin/sellers and /admin/users.
    $this->actingAs($superAdmin)->get(route('admin.sellers.index'))->assertOk();
    $this->actingAs($superAdmin)->get(route('admin.users.index'))->assertOk();
});

test('super admin can update staff permissions dynamically with audit log', function (): void {
    $superAdmin = User::factory()->admin()->create();
    $staffUser = User::factory()->staff([StaffPermission::OrdersManage->value])->create();

    $this->actingAs($staffUser)->get(route('admin.sellers.index'))->assertForbidden();

    $this->actingAs($superAdmin)
        ->patch(route('admin.users.role.update', $staffUser), [
            'role' => UserRole::Staff->value,
            'staff_permissions' => [StaffPermission::SellersReview->value],
        ])
        ->assertRedirect();

    $staffUser->refresh();
    expect($staffUser->resolvedStaffPermissions())->toBe([StaffPermission::SellersReview->value])
        ->and(SecurityAuditLog::query()->where('user_id', $staffUser->id)->where('event', SecurityAuditEvent::StaffPermissionsUpdated->value)->exists())->toBeTrue();

    $this->actingAs($staffUser)->get(route('admin.sellers.index'))->assertOk();
    $this->actingAs($staffUser)->get(route('admin.orders.index'))->assertForbidden();
});

test('seller onboarding creates pending store, stores private verification docs, blocks idor, and requires super admin approval', function (): void {
    Storage::fake('local');
    Notification::fake();

    $applicant = User::factory()->create();
    $otherCustomer = User::factory()->create();
    $reviewerStaff = User::factory()->staff([StaffPermission::SellersReview->value])->create();
    $superAdmin = User::factory()->admin()->create();

    // Admin/Staff cannot submit seller applications.
    $this->actingAs($superAdmin)->get(route('seller.onboarding'))->assertForbidden();

    // Customer submits onboarding application.
    $this->actingAs($applicant)
        ->post(route('seller.onboarding.store'), sellerOnboardingPayload())
        ->assertRedirect(route('seller.onboarding'));

    $applicant->refresh();
    $profile = SellerProfile::query()->where('user_id', $applicant->id)->with('store')->firstOrFail();

    expect($applicant->role)->toBe(UserRole::Seller)
        ->and($profile->status)->toBe(SellerStatus::Pending)
        ->and($profile->store)->not->toBeNull()
        ->and($profile->store?->status)->toBe(StoreStatus::Pending)
        ->and(StoreMembership::query()->where('store_id', $profile->store_id)->where('user_id', $applicant->id)->where('role', 'owner')->exists())->toBeTrue();

    // Unapproved seller is redirected away from GET /seller/dashboard and denied JSON.
    $this->actingAs($applicant)->get(route('seller.dashboard'))->assertRedirect(route('seller.onboarding'));
    $this->actingAs($applicant)->getJson(route('seller.dashboard'))->assertForbidden();
    $this->actingAs($otherCustomer)->get(route('seller.dashboard'))->assertForbidden();

    // Spoofed/corrupted binary disguised as PDF is rejected by finfo inspection.
    $fakeExecutable = UploadedFile::fake()->createWithContent('payload.pdf', "<?php echo 'hack'; ?>");
    $this->actingAs($applicant)
        ->post(route('seller.onboarding.documents.store'), [
            'document_type' => SellerDocumentType::GovernmentId->value,
            'document' => $fakeExecutable,
        ])
        ->assertSessionHasErrors('document');

    // Genuine minimal PDF succeeds on private local disk.
    $validPdf = UploadedFile::fake()->createWithContent('business-permit.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    $this->actingAs($applicant)
        ->post(route('seller.onboarding.documents.store'), [
            'document_type' => SellerDocumentType::BusinessRegistration->value,
            'document' => $validPdf,
        ])
        ->assertRedirect(route('seller.onboarding'));

    $document = SellerVerificationDocument::query()->where('seller_profile_id', $profile->id)->firstOrFail();
    expect($document->disk)->toBe('local');
    Storage::disk('local')->assertExists($document->path);

    // Owner and reviewer can download; unrelated customer gets 403 IDOR block.
    $this->actingAs($applicant)->get(route('seller.onboarding.documents.download', $document))->assertOk();
    $this->actingAs($otherCustomer)->get(route('seller.onboarding.documents.download', $document))->assertForbidden();
    $this->actingAs($otherCustomer)->delete(route('seller.onboarding.documents.destroy', $document))->assertForbidden();

    // Cross-profile admin document mismatch also blocked by policy.
    $secondApplicant = User::factory()->create();
    $this->actingAs($secondApplicant)->post(route('seller.onboarding.store'), sellerOnboardingPayload([
        'store_name' => 'Cebu Acoustic Lab',
        'store_slug' => 'cebu-acoustic-lab',
    ]));
    $secondProfile = SellerProfile::query()->where('user_id', $secondApplicant->id)->firstOrFail();

    $this->actingAs($reviewerStaff)
        ->get(route('admin.sellers.documents.download', [$secondProfile, $document]))
        ->assertForbidden();

    $this->actingAs($reviewerStaff)
        ->get(route('admin.sellers.documents.download', [$profile, $document]))
        ->assertOk();

    // Reviewer Staff can mark Pending -> UnderReview, but is forbidden from approving or rejecting.
    $this->actingAs($reviewerStaff)
        ->patch(route('admin.sellers.status.update', $profile), [
            'status' => SellerStatus::UnderReview->value,
        ])
        ->assertRedirect(route('admin.sellers.show', $profile));

    $profile->refresh();
    expect($profile->status)->toBe(SellerStatus::UnderReview);

    $this->actingAs($reviewerStaff)
        ->patch(route('admin.sellers.status.update', $profile), [
            'status' => SellerStatus::Approved->value,
        ])
        ->assertForbidden();

    $this->actingAs($reviewerStaff)
        ->patch(route('admin.sellers.status.update', $profile), [
            'status' => SellerStatus::Rejected->value,
            'reason' => 'Not allowed by staff.',
        ])
        ->assertForbidden();

    // Super Admin rejecting without reason fails validation.
    $this->actingAs($superAdmin)
        ->patch(route('admin.sellers.status.update', $profile), [
            'status' => SellerStatus::Rejected->value,
            'reason' => '',
        ])
        ->assertSessionHasErrors('status');

    // Super Admin rejects with reason -> seller notified and can resubmit.
    $this->actingAs($superAdmin)
        ->patch(route('admin.sellers.status.update', $profile), [
            'status' => SellerStatus::Rejected->value,
            'reason' => 'Please upload a clearer registration permit.',
        ])
        ->assertRedirect(route('admin.sellers.show', $profile));

    Notification::assertSentTo($applicant, SellerApplicationStatusNotification::class);

    $profile->refresh();
    expect($profile->status)->toBe(SellerStatus::Rejected);

    // Resubmit by rejected seller resets to Pending and clears rejection_reason.
    $this->actingAs($applicant)
        ->post(route('seller.onboarding.store'), sellerOnboardingPayload([
            'store_name' => 'Manila Sound Studio Pro',
        ]))
        ->assertRedirect(route('seller.onboarding'));

    $profile->refresh();
    expect($profile->status)->toBe(SellerStatus::Pending)
        ->and($profile->rejection_reason)->toBeNull();

    // Super Admin approves -> Store becomes active and /seller/dashboard unlocks with store-scoped counts.
    $this->actingAs($superAdmin)
        ->patch(route('admin.sellers.status.update', $profile), [
            'status' => SellerStatus::Approved->value,
        ])
        ->assertRedirect(route('admin.sellers.show', $profile));

    $profile->refresh();
    $profile->store?->refresh();
    expect($profile->status)->toBe(SellerStatus::Approved)
        ->and($profile->store?->status)->toBe(StoreStatus::Active)
        ->and($applicant->fresh()->hasApprovedStore())->toBeTrue();

    // Seed 1 product in applicant's store and 1 product in second store to assert strict scoping.
    $category = Category::factory()->create();
    Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $profile->store_id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 3,
    ]);
    Product::factory()->create([
        'category_id' => $category->id,
        'store_id' => $secondProfile->store_id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 25,
    ]);

    $this->actingAs($applicant)
        ->get(route('seller.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/Dashboard')
            ->where('store.id', $profile->store_id)
            ->where('metrics.total_products', 1)
            ->where('metrics.low_stock_products', 1)
        );

    // Super Admin suspends seller -> /seller/dashboard locks immediately; reactivation restores access.
    $this->actingAs($superAdmin)
        ->patch(route('admin.sellers.status.update', $profile), [
            'status' => SellerStatus::Suspended->value,
            'reason' => 'Compliance review hold.',
        ])
        ->assertRedirect(route('admin.sellers.show', $profile));

    $this->actingAs($applicant)->get(route('seller.dashboard'))->assertRedirect(route('seller.onboarding'));

    $this->actingAs($superAdmin)
        ->patch(route('admin.sellers.status.update', $profile), [
            'status' => SellerStatus::Approved->value,
        ])
        ->assertRedirect(route('admin.sellers.show', $profile));

    $this->actingAs($applicant)->get(route('seller.dashboard'))->assertOk();
});

test('login redirect respects portal hierarchy and SaveProductAction links official store by default', function (): void {
    $customer = User::factory()->create(['password' => 'Passw0rd!2026']);
    $reviewer = User::factory()->staff([StaffPermission::SellersReview->value])->create(['password' => 'Passw0rd!2026']);
    $superAdmin = User::factory()->admin()->create(['password' => 'Passw0rd!2026']);

    $this->post('/login', ['email' => $customer->email, 'password' => 'Passw0rd!2026'])
        ->assertRedirect('/dashboard');
    $this->post('/logout');

    $this->post('/login', ['email' => $reviewer->email, 'password' => 'Passw0rd!2026'])
        ->assertRedirect('/admin/sellers');
    $this->post('/logout');

    $this->post('/login', ['email' => $superAdmin->email, 'password' => 'Passw0rd!2026'])
        ->assertRedirect('/admin/operations');
    $this->post('/logout');

    // SaveProductAction assigns the active official store when store_id is omitted.
    $category = Category::factory()->create();
    $product = app(SaveProductAction::class)->execute(null, [
        'category_id' => $category->id,
        'name' => 'Official Flagship Mic',
        'sku' => 'XHP-MIC-001',
        'status' => ProductStatus::Active->value,
        'base_price' => '1999.00',
        'stock_quantity' => 10,
    ]);

    $officialStore = Store::query()->where('is_official', true)->first();
    expect($officialStore)->not->toBeNull()
        ->and($product->store_id)->toBe($officialStore?->id);
});

test('wishlist enforces cross-customer isolation and web errors render branded inertia error states', function (): void {
    $customerA = User::factory()->create();
    $customerB = User::factory()->create();

    $category = Category::factory()->create();
    $activeProduct = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
    ]);
    $draftProduct = Product::factory()->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Draft,
    ]);

    // Cannot save draft product to wishlist.
    $this->actingAs($customerA)
        ->post(route('wishlist.toggle', $draftProduct))
        ->assertNotFound();

    // Customer A saves active product.
    $this->actingAs($customerA)
        ->post(route('wishlist.toggle', $activeProduct))
        ->assertRedirect();

    $wishlistItem = WishlistItem::query()
        ->where('user_id', $customerA->id)
        ->where('product_id', $activeProduct->id)
        ->firstOrFail();

    // Customer A sees item on /wishlist and customer dashboard count.
    $this->actingAs($customerA)
        ->get(route('wishlist.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('wishlist/Index')
            ->has('items', 1)
            ->where('items.0.product_id', $activeProduct->id)
        );

    $this->actingAs($customerA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('accountSummary.wishlistCount', 1)
        );

    // Cross-customer IDOR: Customer B cannot delete Customer A's wishlist item.
    $this->actingAs($customerB)
        ->delete(route('wishlist.destroy', $wishlistItem))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 403)
        );

    expect(WishlistItem::query()->whereKey($wishlistItem->id)->exists())->toBeTrue();

    // Unauthorized access to admin route renders branded 403 Inertia Error component.
    $this->actingAs($customerA)
        ->get(route('admin.users.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('status', 403)
        );
});
