<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\CatalogPresenter;
use App\Domain\Catalog\Services\CatalogQueryService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductDetailController extends Controller
{
    public function __construct(
        private readonly CatalogQueryService $catalogQuery,
        private readonly CatalogPresenter $presenter,
        private readonly CartService $cartService,
    ) {}

    public function show(Request $request, string $slug): Response
    {
        $detail = $this->catalogQuery->findActiveProductDetail($slug);

        if ($detail === null) {
            abort(404);
        }

        $categories = Category::query()
            ->active()
            ->withCount([
                'products' => fn (Builder $query): Builder => Product::applyActiveCategoryCountFilter($query),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('catalog/Show', [
            'product' => $detail['product'],
            'relatedProducts' => $detail['relatedProducts'],
            'categories' => $categories->map(fn (Category $category): array => $this->presenter->categorySummary($category))->all(),
            'cartSummary' => $this->cartService->summaryForRequest($request),
        ]);
    }
}
