<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\ManageProductImagesAction;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadProductImageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class ProductImageController extends Controller
{
    public function store(
        UploadProductImageRequest $request,
        Product $product,
        ManageProductImagesAction $action,
    ): RedirectResponse {
        /** @var UploadedFile $file */
        $file = $request->file('image');
        $variantId = $request->filled('variant_id') ? (int) $request->integer('variant_id') : null;

        try {
            $action->upload(
                product: $product,
                file: $file,
                altText: (string) $request->validated('alt_text'),
                variantId: $variantId,
                makePrimary: $request->boolean('is_primary'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'image' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Product image uploaded cleanly.'),
        ]);

        return to_route('admin.catalog.products.edit', ['product' => $product->id])
            ->with('success', 'Product image uploaded cleanly.');
    }

    public function setPrimary(
        Product $product,
        ProductImage $image,
        ManageProductImagesAction $action,
    ): RedirectResponse {
        if ($image->product_id !== $product->id) {
            abort(404);
        }

        $action->setPrimary($product, $image);

        return to_route('admin.catalog.products.edit', ['product' => $product->id])
            ->with('success', 'Primary image updated.');
    }

    public function destroy(
        Product $product,
        ProductImage $image,
        ManageProductImagesAction $action,
    ): RedirectResponse {
        if ($image->product_id !== $product->id) {
            abort(404);
        }

        $action->delete($product, $image);

        return to_route('admin.catalog.products.edit', ['product' => $product->id])
            ->with('success', 'Product image removed.');
    }
}
