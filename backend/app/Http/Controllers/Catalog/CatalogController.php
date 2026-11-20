<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Services\CatalogQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogFilterRequest;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogQueryService $catalogQuery,
        private readonly CartService $cartService,
    ) {}

    public function index(CatalogFilterRequest $request): Response
    {
        /** @var array{
         *     q?: string|null,
         *     category?: string|null,
         *     brand?: string|null,
         *     min_price?: float|int|string|null,
         *     max_price?: float|int|string|null,
         *     in_stock?: bool,
         *     sort?: string|null,
         *     per_page?: int|null
         * } $validated */
        $validated = $request->validated();

        $catalog = $this->catalogQuery->paginateCatalog($validated);

        return Inertia::render('catalog/Index', [
            ...$catalog,
            'cartSummary' => $this->cartService->summaryForRequest($request),
        ]);
    }
}
