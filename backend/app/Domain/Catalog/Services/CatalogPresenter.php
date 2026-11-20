<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\ProductVariant;

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
    public function productSummary(Product $product): array
    {
        $unitPrice = $product->resolvedUnitPrice();
        $compareAt = $product->resolvedCompareAtPrice();
        $totalStock = $product->totalAvailableStock();
        $primaryImage = $product->images->first();

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
            'sellerName' => $product->seller_name,
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
}
