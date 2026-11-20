<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Services\CatalogMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class ManageProductImagesAction
{
    public function __construct(
        private readonly CatalogMediaService $mediaService,
    ) {}

    public function upload(
        Product $product,
        UploadedFile $file,
        string $altText,
        ?int $variantId = null,
        bool $makePrimary = false,
    ): ProductImage {
        $stored = $this->mediaService->storeProductImage($product->id, $file);

        return DB::transaction(function () use ($product, $stored, $altText, $variantId, $makePrimary): ProductImage {
            $hasExisting = $product->images()->exists();
            $shouldBePrimary = $makePrimary || ! $hasExisting;

            if ($shouldBePrimary) {
                $product->images()->update(['is_primary' => false]);
            }

            $nextSort = ((int) $product->images()->max('sort_order')) + 1;

            return ProductImage::query()->create([
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'alt_text' => trim($altText) !== '' ? trim($altText) : $product->name,
                'sort_order' => $nextSort,
                'is_primary' => $shouldBePrimary,
                'mime_type' => $stored['mime_type'],
                'size_bytes' => $stored['size_bytes'],
            ]);
        });
    }

    public function setPrimary(Product $product, ProductImage $image): void
    {
        if ($image->product_id !== $product->id) {
            return;
        }

        DB::transaction(function () use ($product, $image): void {
            $product->images()->update(['is_primary' => false]);
            $image->forceFill(['is_primary' => true])->save();
        });
    }

    public function delete(Product $product, ProductImage $image): void
    {
        if ($image->product_id !== $product->id) {
            return;
        }

        $disk = $image->disk;
        $path = $image->path;
        $wasPrimary = $image->is_primary;

        DB::transaction(function () use ($product, $image, $wasPrimary): void {
            $image->delete();

            if ($wasPrimary) {
                $replacement = $product->images()->orderBy('sort_order')->orderBy('id')->first();
                if ($replacement instanceof ProductImage) {
                    $replacement->forceFill(['is_primary' => true])->save();
                }
            }
        });

        $this->mediaService->deleteStoredImage($disk, $path);
    }
}
