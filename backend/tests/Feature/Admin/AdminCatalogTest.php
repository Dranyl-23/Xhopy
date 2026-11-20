<?php

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guests and regular customer accounts are denied access to admin catalog workflows', function () {
    $customer = User::factory()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $this->get(route('admin.catalog.index'))->assertRedirect(route('login'));

    $this->actingAs($customer)->get(route('admin.catalog.index'))->assertForbidden();
    $this->actingAs($customer)->post(route('admin.catalog.products.store'), [])->assertForbidden();
    $this->actingAs($customer)->patch(route('admin.catalog.products.update', $product), [])->assertForbidden();
    $this->actingAs($customer)->delete(route('admin.catalog.products.destroy', $product))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.catalog.categories.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.catalog.brands.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.catalog.campaigns.index'))->assertForbidden();
});

test('staff and admin can create and update products with variants and server recomputes base price and stock', function () {
    $staff = User::factory()->staff()->create();
    $category = Category::factory()->create();
    $brand = Brand::factory()->create();

    $response = $this->actingAs($staff)->post(route('admin.catalog.products.store'), [
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'seller_name' => 'XHopy Official',
        'name' => 'Acoustic Dock Pro',
        'sku' => 'dock-pro-01',
        'status' => 'active',
        'is_featured' => true,
        'currency' => 'PHP',
        'base_price' => '1000.00',
        'stock_quantity' => 0,
        'specifications' => ['Material' => 'Anodized Aluminum'],
        'variants' => [
            [
                'title' => 'Silver',
                'sku' => 'dock-pro-slv',
                'price' => '4200.00',
                'stock_quantity' => 7,
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'title' => 'Crimson',
                'sku' => 'dock-pro-crm',
                'price' => '4500.00',
                'stock_quantity' => 3,
                'is_default' => false,
                'is_active' => true,
            ],
        ],
    ]);

    /** @var Product $product */
    $product = Product::query()->where('sku', 'DOCK-PRO-01')->firstOrFail();

    $response->assertRedirect(route('admin.catalog.products.edit', $product));
    expect((string) $product->base_price)->toBe('4200.00')
        ->and($product->stock_quantity)->toBe(10)
        ->and($product->variants()->count())->toBe(2);
});

test('admin image upload validates real image MIME and blocks cross-product variant or image IDOR', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    $productA = Product::factory()->create(['category_id' => $category->id]);
    $productB = Product::factory()->create(['category_id' => $category->id]);
    $variantOnB = ProductVariant::factory()->create(['product_id' => $productB->id]);

    // Reject SVG script upload
    $svgUpload = UploadedFile::fake()->createWithContent(
        'malicious.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    $this->actingAs($admin)
        ->post(route('admin.catalog.products.images.store', $productA), [
            'image' => $svgUpload,
            'alt_text' => 'Malicious SVG',
        ])
        ->assertSessionHasErrors(['image']);

    // Reject associating product B's variant while uploading to product A
    $validPng = UploadedFile::fake()->image('valid.png', 120, 120);
    $this->actingAs($admin)
        ->post(route('admin.catalog.products.images.store', $productA), [
            'image' => $validPng,
            'alt_text' => 'Valid PNG',
            'variant_id' => $variantOnB->id,
        ])
        ->assertSessionHasErrors(['variant_id']);

    // Upload valid image to product A then try deleting it via product B endpoint -> 404
    $this->actingAs($admin)
        ->post(route('admin.catalog.products.images.store', $productA), [
            'image' => UploadedFile::fake()->image('clean.png', 120, 120),
            'alt_text' => 'Clean Studio Shot',
            'is_primary' => true,
        ])
        ->assertRedirect(route('admin.catalog.products.edit', $productA));

    /** @var ProductImage $image */
    $image = $productA->images()->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('admin.catalog.products.images.destroy', [
            'product' => $productB->id,
            'image' => $image->id,
        ]))
        ->assertNotFound();

    expect(Storage::disk('local')->exists($image->path))->toBeTrue();
});

test('admin category delete blocks non-empty category and campaign href rejects unsafe schemes', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    $this->actingAs($admin)
        ->delete(route('admin.catalog.categories.destroy', $category))
        ->assertSessionHasErrors(['category']);

    $this->actingAs($admin)
        ->post(route('admin.catalog.campaigns.store'), [
            'placement' => 'daily_interest_tile',
            'tag' => 'Guide',
            'title' => 'Unsafe CTA Campaign',
            'cta_href' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors(['cta_href']);
});
