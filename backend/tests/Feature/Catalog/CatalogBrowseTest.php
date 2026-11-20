<?php

use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('storefront home displays real active categories, featured products, and active daily interest campaigns', function () {
    $category = Category::factory()->create(['name' => 'Acoustic Gear', 'slug' => 'acoustic-gear', 'is_active' => true]);
    $hiddenCategory = Category::factory()->create(['name' => 'Hidden Dept', 'slug' => 'hidden-dept', 'is_active' => false]);

    Product::factory()->featured()->create([
        'category_id' => $category->id,
        'name' => 'Reference Planar Headphones',
        'status' => ProductStatus::Active,
    ]);

    Product::factory()->featured()->create([
        'category_id' => $category->id,
        'name' => 'Unpublished Draft Monitor',
        'status' => ProductStatus::Draft,
    ]);

    Product::factory()->featured()->create([
        'category_id' => $hiddenCategory->id,
        'name' => 'Hidden Category Speaker',
        'status' => ProductStatus::Active,
    ]);

    CampaignContent::factory()->create([
        'placement' => CampaignPlacement::Announcement,
        'title' => 'Verified Announcement',
        'cta_label' => 'Shop Catalog',
        'cta_href' => '/catalog',
        'is_active' => true,
    ]);

    CampaignContent::factory()->create([
        'placement' => CampaignPlacement::DailyInterestFeatured,
        'tag' => 'Editorial',
        'title' => 'Workspace Deep Dive',
        'is_active' => true,
    ]);

    CampaignContent::factory()->create([
        'placement' => CampaignPlacement::DailyInterestTile,
        'tag' => 'Guide',
        'title' => 'Audio Setup Guide',
        'is_active' => true,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('isDemoPreview', false)
            ->has('categories', 1)
            ->where('categories.0.slug', 'acoustic-gear')
            ->where('categories.0.productCount', 1)
            ->has('topProducts', 1)
            ->where('topProducts.0.name', 'Reference Planar Headphones')
            ->where('announcement.message', 'Verified Announcement')
            ->where('dailyInterest.featured.title', 'Workspace Deep Dive')
            ->has('dailyInterest.tiles', 1)
        );
});

test('catalog listing filters by category including child subcategories, brand, price range, search including wildcards, stock, sort, and bounded pagination', function () {
    $parent = Category::factory()->create(['name' => 'Audio', 'slug' => 'audio']);
    $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'DACs', 'slug' => 'dacs']);
    $otherCategory = Category::factory()->create(['name' => 'Desk', 'slug' => 'desk']);

    $brandA = Brand::factory()->create(['name' => 'XHopy Studio', 'slug' => 'xhopy-studio']);
    $brandB = Brand::factory()->create(['name' => 'OtherBrand', 'slug' => 'other-brand']);

    $prod1 = Product::factory()->create([
        'category_id' => $child->id,
        'brand_id' => $brandA->id,
        'name' => 'Alpha Compact 100% Amp',
        'sku' => 'AMP-100',
        'base_price' => '2500.00',
        'stock_quantity' => 5,
    ]);
    ProductVariant::factory()->create([
        'product_id' => $prod1->id,
        'sku' => 'AMP-100-BAL',
        'price' => '2500.00',
        'stock_quantity' => 5,
        'is_default' => true,
    ]);

    Product::factory()->create([
        'category_id' => $parent->id,
        'brand_id' => $brandA->id,
        'name' => 'Beta Studio Headphones',
        'sku' => 'HDP-200',
        'base_price' => '6000.00',
        'stock_quantity' => 0,
    ]);

    Product::factory()->create([
        'category_id' => $otherCategory->id,
        'brand_id' => $brandB->id,
        'name' => 'Gamma Desk Lamp',
        'sku' => 'LMP-300',
        'base_price' => '1500.00',
        'stock_quantity' => 10,
    ]);

    // Child category matches parent category filter + price sort ascending
    $this->get(route('catalog.index', [
        'category' => 'audio',
        'sort' => 'price_asc',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Index')
            ->has('products', 2)
            ->where('products.0.sku', 'AMP-100')
            ->where('products.1.sku', 'HDP-200')
        );

    // Search escapes literal '%' wildcard (must NOT match Gamma Desk Lamp!) + matches variant SKU
    $this->get(route('catalog.index', ['q' => '100%']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.sku', 'AMP-100')
        );

    $this->get(route('catalog.index', ['q' => 'AMP-100-BAL']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.sku', 'AMP-100')
        );

    // Brand + in_stock + min/max price
    $this->get(route('catalog.index', [
        'brand' => 'xhopy-studio',
        'in_stock' => '1',
        'min_price' => '2000',
        'max_price' => '3000',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.sku', 'AMP-100')
        );
});

test('catalog rejects invalid sort allowlist or unbounded page size at server boundary', function () {
    $this->get(route('catalog.index', ['sort' => 'base_price; DROP TABLE products']))
        ->assertSessionHasErrors(['sort']);

    $this->get(route('catalog.index', ['per_page' => 500]))
        ->assertSessionHasErrors(['per_page']);
});
