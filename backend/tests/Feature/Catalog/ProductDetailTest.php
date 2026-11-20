<?php

use App\Domain\Catalog\Actions\ManageProductImagesAction;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('active product detail shows gallery, active variants, specifications, and related products', function () {
    Storage::fake('local');

    $category = Category::factory()->create(['slug' => 'audio']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Studio Monitors Pro',
        'slug' => 'studio-monitors-pro',
        'sku' => 'MON-01',
        'status' => ProductStatus::Active,
        'specifications' => ['Impedance' => '42 Ohms'],
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'title' => 'Matte Black',
        'sku' => 'MON-01-BLK',
        'price' => '7500.00',
        'stock_quantity' => 4,
        'is_default' => true,
        'is_active' => true,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'title' => 'Hidden Retired Variant',
        'sku' => 'MON-01-RET',
        'is_active' => false,
    ]);

    Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Related Acoustic Cable',
        'status' => ProductStatus::Active,
    ]);

    $imageAction = app(ManageProductImagesAction::class);
    $image = $imageAction->upload(
        product: $product,
        file: UploadedFile::fake()->image('studio.png', 200, 200),
        altText: 'Studio monitor front view',
        makePrimary: true,
    );

    $this->get(route('products.show', ['product' => 'studio-monitors-pro']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Show')
            ->where('product.name', 'Studio Monitors Pro')
            ->has('product.variants', 1)
            ->where('product.variants.0.sku', 'MON-01-BLK')
            ->where('product.specifications.Impedance', '42 Ohms')
            ->has('product.images', 1)
            ->has('relatedProducts', 1)
        );

    $this->get(route('catalog.media.show', ['productImage' => $image->id]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('draft product detail and media return 404 to public customers and allow catalog managers on media', function () {
    Storage::fake('local');

    $category = Category::factory()->create();
    $draft = Product::factory()->draft()->create([
        'category_id' => $category->id,
        'slug' => 'secret-draft-item',
    ]);

    $image = app(ManageProductImagesAction::class)->upload(
        product: $draft,
        file: UploadedFile::fake()->image('draft.png', 100, 100),
        altText: 'Draft prototype image',
        makePrimary: true,
    );

    $customer = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->get(route('products.show', ['product' => 'secret-draft-item']))->assertNotFound();
    $this->actingAs($customer)->get(route('catalog.media.show', ['productImage' => $image->id]))->assertNotFound();
    $this->actingAs($admin)->get(route('catalog.media.show', ['productImage' => $image->id]))->assertOk();
});
