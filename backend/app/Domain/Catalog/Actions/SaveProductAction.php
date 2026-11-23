<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Seller\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaveProductAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $variants
     */
    public function execute(?Product $product, array $data, array $variants = []): Product
    {
        return DB::transaction(function () use ($product, $data, $variants): Product {
            $status = $data['status'] instanceof ProductStatus
                ? $data['status']
                : ProductStatus::from((string) $data['status']);

            $slug = trim((string) ($data['slug'] ?? ''));
            if ($slug === '') {
                $slug = Str::slug((string) $data['name']);
            }

            $basePrice = number_format((float) $data['base_price'], 2, '.', '');
            $compareAtPrice = isset($data['compare_at_price']) && $data['compare_at_price'] !== ''
                ? number_format((float) $data['compare_at_price'], 2, '.', '')
                : null;
            $stockQuantity = max(0, (int) ($data['stock_quantity'] ?? 0));

            $target = $product ?? new Product;

            $rawStoreId = $data['store_id'] ?? $target->store_id ?? Store::query()->where('is_official', true)->value('id');
            $storeId = is_numeric($rawStoreId) && (int) $rawStoreId > 0 ? (int) $rawStoreId : null;

            $storeName = null;
            if ($storeId !== null) {
                $rawStoreName = Store::query()->whereKey($storeId)->value('name');
                if (is_string($rawStoreName) && trim($rawStoreName) !== '') {
                    $storeName = trim($rawStoreName);
                }
            }

            $sellerName = trim((string) ($data['seller_name'] ?? ''));
            if ($sellerName === '') {
                $sellerName = $storeName ?? 'XHopy Official';
            }

            $submittedForReviewAt = $status === ProductStatus::PendingReview
                ? now()
                : $target->submitted_for_review_at;

            $moderationNotes = in_array($status, [ProductStatus::Active, ProductStatus::PendingReview], true)
                ? null
                : $target->moderation_notes;

            $target->fill([
                'category_id' => (int) $data['category_id'],
                'brand_id' => isset($data['brand_id']) && $data['brand_id'] !== '' ? (int) $data['brand_id'] : null,
                'store_id' => $storeId,
                'seller_name' => $sellerName,
                'name' => trim((string) $data['name']),
                'slug' => $slug,
                'sku' => strtoupper(trim((string) $data['sku'])),
                'short_description' => isset($data['short_description']) ? trim((string) $data['short_description']) : null,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                'status' => $status,
                'moderation_notes' => $moderationNotes,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'currency' => strtoupper((string) ($data['currency'] ?? 'PHP')),
                'base_price' => $basePrice,
                'compare_at_price' => $compareAtPrice,
                'stock_quantity' => $stockQuantity,
                'specifications' => is_array($data['specifications'] ?? null) ? $data['specifications'] : null,
                'published_at' => $status === ProductStatus::Active
                    ? ($target->published_at ?? now())
                    : $target->published_at,
                'submitted_for_review_at' => $submittedForReviewAt,
            ]);
            $target->save();

            $this->syncVariants($target, $variants);

            return $target->fresh(['category', 'brand', 'variants', 'images']) ?? $target;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        if ($variants === []) {
            $product->variants()->delete();

            return;
        }

        $retainedIds = [];
        $hasDefault = false;

        foreach ($variants as $index => $variantData) {
            $isDefault = (bool) ($variantData['is_default'] ?? false);
            if ($isDefault && $hasDefault) {
                $isDefault = false;
            }
            if ($isDefault) {
                $hasDefault = true;
            }

            $variantId = isset($variantData['id']) ? (int) $variantData['id'] : null;
            /** @var ProductVariant|null $variant */
            $variant = $variantId !== null
                ? ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('id', $variantId)
                    ->first()
                : null;

            $variant ??= new ProductVariant(['product_id' => $product->id]);
            $variant->fill([
                'product_id' => $product->id,
                'title' => trim((string) $variantData['title']),
                'sku' => strtoupper(trim((string) $variantData['sku'])),
                'price' => number_format((float) $variantData['price'], 2, '.', ''),
                'compare_at_price' => isset($variantData['compare_at_price']) && $variantData['compare_at_price'] !== ''
                    ? number_format((float) $variantData['compare_at_price'], 2, '.', '')
                    : null,
                'currency' => $product->currency,
                'stock_quantity' => max(0, (int) ($variantData['stock_quantity'] ?? 0)),
                'options' => is_array($variantData['options'] ?? null) ? $variantData['options'] : null,
                'is_default' => $isDefault,
                'is_active' => (bool) ($variantData['is_active'] ?? true),
                'sort_order' => $index,
            ]);
            $variant->save();
            $retainedIds[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $retainedIds)->delete();

        $activeVariants = $product->variants()->where('is_active', true)->orderBy('sort_order')->get();
        if ($activeVariants->isNotEmpty()) {
            $defaultVariant = $activeVariants->firstWhere('is_default', true) ?? $activeVariants->first();
            if (! $defaultVariant->is_default) {
                $defaultVariant->forceFill(['is_default' => true])->save();
            }

            $product->forceFill([
                'base_price' => $defaultVariant->price,
                'compare_at_price' => $defaultVariant->compare_at_price,
                'stock_quantity' => (int) $activeVariants->sum('stock_quantity'),
            ])->save();
        }
    }
}
