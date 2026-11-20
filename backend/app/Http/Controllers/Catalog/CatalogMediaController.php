<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\ProductImage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogMediaController extends Controller
{
    public function show(Request $request, ProductImage $productImage): StreamedResponse
    {
        $productImage->load('product.category');
        $product = $productImage->product;

        $isPublished = $product->status === ProductStatus::Active
            && $product->category->is_active;

        if (! $isPublished && ! ($request->user()?->canManageCatalog() ?? false)) {
            abort(404);
        }

        $storage = Storage::disk($productImage->disk);
        if (! $storage->exists($productImage->path)) {
            abort(404);
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $isPublished ? 'public, max-age=86400' : 'private, no-store',
        ];

        if ($productImage->mime_type !== null && $productImage->mime_type !== '') {
            $headers['Content-Type'] = $productImage->mime_type;
        }

        return $storage->response($productImage->path, headers: $headers);
    }
}
