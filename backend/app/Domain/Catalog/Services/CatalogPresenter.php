<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Seller\Models\Store;
use Illuminate\Http\UploadedFile;

final class CatalogPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function categorySummary(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'productCount' => (int) ($category->products_count ?? 0),
            'imageUrl' => $category->image_path,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function brandSummary(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'description' => $brand->description,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function storeSummary(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'description' => $store->description,
            'logoUrl' => null,
            'status' => $store->status->value,
            'statusLabel' => $store->status->label(),
            'isOfficial' => $store->is_official,
            'productCount' => (int) ($store->getAttribute('published_products_count') ?? $store->getAttribute('products_count') ?? 0),
            'url' => route('stores.show', ['store' => $store->slug]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productSummary(Product $product): array
    {
        $unitPrice = $product->resolvedUnitPrice();
        $compareAt = $product->resolvedCompareAtPrice();
        $totalStock = $product->totalAvailableStock();
        $primaryImage = $product->images->first();
        $activeStore = $product->relationLoaded('store')
            && $product->store instanceof Store
            && $product->store->isActive()
            ? $product->store
            : null;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'url' => route('products.show', ['product' => $product->slug]),
            'categoryName' => $product->category->name,
            'categorySlug' => $product->category->slug,
            'brandName' => $product->brand?->name,
            'brandSlug' => $product->brand?->slug,
            'sellerName' => $activeStore instanceof Store ? $activeStore->name : $product->seller_name,
            'storeId' => $activeStore?->id,
            'storeSlug' => $activeStore?->slug,
            'storeUrl' => $activeStore instanceof Store
                ? route('stores.show', ['store' => $activeStore->slug])
                : null,
            'isOfficialStore' => $activeStore instanceof Store ? $activeStore->is_official : true,
            'unitPrice' => MoneyFormatter::toFloatAmount($unitPrice),
            'priceFormatted' => MoneyFormatter::format($unitPrice, $product->currency),
            'originalPrice' => $compareAt !== null ? MoneyFormatter::toFloatAmount($compareAt) : null,
            'originalPriceFormatted' => $compareAt !== null ? MoneyFormatter::format($compareAt, $product->currency) : null,
            'discountLabel' => MoneyFormatter::discountLabel($unitPrice, $compareAt, $product->currency),
            'currency' => $product->currency,
            'inStock' => $totalStock > 0,
            'stockLabel' => $totalStock > 0 ? 'In Stock' : 'Out of Stock',
            'imageUrl' => $primaryImage instanceof ProductImage ? $this->imageUrl($primaryImage) : null,
            'imageAlt' => $primaryImage instanceof ProductImage ? $primaryImage->alt_text : $product->name,
            'ratingAverage' => null,
            'ratingCount' => null,
            'hasVariants' => $product->variants->where('is_active', true)->isNotEmpty(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productDetail(Product $product): array
    {
        $activeVariants = $product->variants
            ->where('is_active', true)
            ->values();

        $summary = $this->productSummary($product);

        return [
            ...$summary,
            'shortDescription' => $product->short_description,
            'description' => $product->description,
            'stockQuantity' => $product->totalAvailableStock(),
            'specifications' => $product->specifications ?? [],
            'images' => $product->images->values()->map(fn (ProductImage $image): array => [
                'id' => $image->id,
                'variantId' => $image->variant_id,
                'url' => $this->imageUrl($image),
                'alt' => $image->alt_text,
                'isPrimary' => $image->is_primary,
            ])->all(),
            'variants' => $activeVariants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'title' => $variant->title,
                'sku' => $variant->sku,
                'unitPrice' => MoneyFormatter::toFloatAmount($variant->price),
                'priceFormatted' => MoneyFormatter::format($variant->price, $variant->currency),
                'originalPrice' => $variant->compare_at_price !== null
                    ? MoneyFormatter::toFloatAmount($variant->compare_at_price)
                    : null,
                'originalPriceFormatted' => $variant->compare_at_price !== null
                    ? MoneyFormatter::format($variant->compare_at_price, $variant->currency)
                    : null,
                'discountLabel' => MoneyFormatter::discountLabel(
                    $variant->price,
                    $variant->compare_at_price,
                    $variant->currency,
                ),
                'currency' => $variant->currency,
                'stockQuantity' => max(0, $variant->stock_quantity),
                'inStock' => $variant->stock_quantity > 0,
                'stockLabel' => $variant->stock_quantity > 0
                    ? ($variant->stock_quantity <= 5 ? "Low stock ({$variant->stock_quantity} left)" : 'In Stock')
                    : 'Out of Stock',
                'options' => $variant->options ?? [],
                'isDefault' => $variant->is_default,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function campaignItem(CampaignContent $campaign): array
    {
        $href = $campaign->cta_href;

        if (($href === null || $href === '') && $campaign->category !== null) {
            $href = route('catalog.index', ['category' => $campaign->category->slug]);
        }

        return [
            'id' => (string) $campaign->id,
            'tag' => $campaign->tag,
            'title' => $campaign->title,
            'summary' => (string) ($campaign->summary ?? ''),
            'ctaLabel' => (string) ($campaign->cta_label ?: 'Explore Collection'),
            'href' => (string) ($href ?: route('catalog.index')),
            'imageUrl' => $campaign->image_path,
            'imageAlt' => $campaign->image_alt,
        ];
    }

    public function imageUrl(ProductImage $image): string
    {
        return route('catalog.media.show', ['productImage' => $image->id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function managementProductRow(Product $product): array
    {
        $firstImage = $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'status' => $product->status->value,
            'statusLabel' => $product->status->label(),
            'moderationNotes' => $product->moderation_notes,
            'isFeatured' => $product->is_featured,
            'storeId' => $product->store_id,
            'storeName' => $product->store->name ?? $product->seller_name,
            'isOfficialStore' => (bool) ($product->store->is_official ?? true),
            'categoryName' => $product->category->name,
            'brandName' => $product->brand?->name,
            'priceFormatted' => MoneyFormatter::format($product->resolvedUnitPrice(), $product->currency),
            'stockQuantity' => $product->totalAvailableStock(),
            'variantCount' => $product->variants->count(),
            'imageUrl' => $firstImage instanceof ProductImage
                ? $this->imageUrl($firstImage)
                : null,
            'submittedForReviewAtDiff' => $product->submitted_for_review_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editableProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'store_id' => $product->store_id,
            'store_name' => $product->store->name ?? $product->seller_name,
            'is_official_store' => (bool) ($product->store->is_official ?? true),
            'seller_name' => $product->seller_name,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => $product->short_description ?? '',
            'description' => $product->description ?? '',
            'status' => $product->status->value,
            'status_label' => $product->status->label(),
            'moderation_notes' => $product->moderation_notes,
            'submitted_for_review_at' => $product->submitted_for_review_at?->toDayDateTimeString(),
            'moderated_at' => $product->moderated_at?->toDayDateTimeString(),
            'moderator_name' => $product->moderator?->name,
            'is_featured' => $product->is_featured,
            'currency' => $product->currency,
            'base_price' => (string) $product->base_price,
            'compare_at_price' => $product->compare_at_price !== null ? (string) $product->compare_at_price : '',
            'stock_quantity' => $product->stock_quantity,
            'specifications' => $product->specifications ?? [],
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'title' => $variant->title,
                'sku' => $variant->sku,
                'price' => (string) $variant->price,
                'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : '',
                'stock_quantity' => $variant->stock_quantity,
                'options' => $variant->options ?? [],
                'is_default' => $variant->is_default,
                'is_active' => $variant->is_active,
            ])->all(),
            'images' => $product->images->map(fn (ProductImage $image): array => [
                'id' => $image->id,
                'variant_id' => $image->variant_id,
                'url' => $this->imageUrl($image),
                'alt_text' => $image->alt_text,
                'is_primary' => $image->is_primary,
            ])->all(),
        ];
    }

    /**
     * @param  list<ProductStatus>  $statuses
     * @return array<string, mixed>
     */
    public function formReferenceData(array $statuses): array
    {
        $maxUploadBytes = min(5 * 1024 * 1024, max(512 * 1024, (int) UploadedFile::getMaxFilesize()));

        return [
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'is_active']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name', 'slug', 'is_active']),
            'statuses' => array_map(
                static fn (ProductStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                $statuses,
            ),
            'maxImageUploadBytes' => $maxUploadBytes,
        ];
    }
}
