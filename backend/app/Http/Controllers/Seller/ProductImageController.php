<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Catalog\Actions\ManageProductImagesAction;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Seller\Services\SellerWorkspaceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\UploadSellerProductImageRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;

class ProductImageController extends Controller
{
    public function __construct(
        private readonly SellerWorkspaceService $workspace,
    ) {}

    public function store(
        UploadSellerProductImageRequest $request,
        Product $product,
        ManageProductImagesAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $this->workspace->requireApprovedWorkspace($actor);
        Gate::forUser($actor)->authorize('manageOwned', $product);

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

        return to_route('seller.products.edit', ['product' => $product->id]);
    }

    public function setPrimary(
        Request $request,
        Product $product,
        ProductImage $image,
        ManageProductImagesAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $this->workspace->requireApprovedWorkspace($actor);
        Gate::forUser($actor)->authorize('manageOwned', $product);

        if ($image->product_id !== $product->id) {
            abort(404);
        }

        $action->setPrimary($product, $image);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Primary image updated.'),
        ]);

        return to_route('seller.products.edit', ['product' => $product->id]);
    }

    public function destroy(
        Request $request,
        Product $product,
        ProductImage $image,
        ManageProductImagesAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $this->workspace->requireApprovedWorkspace($actor);
        Gate::forUser($actor)->authorize('manageOwned', $product);

        if ($image->product_id !== $product->id) {
            abort(404);
        }

        $action->delete($product, $image);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Product image removed.'),
        ]);

        return to_route('seller.products.edit', ['product' => $product->id]);
    }
}
