<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Services\CatalogQueryService;
use App\Domain\Seller\Models\Store;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CatalogFilterRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontStoreController extends Controller
{
    public function __construct(
        private readonly CatalogQueryService $catalogQuery,
        private readonly CartService $cartService,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));

        return Inertia::render('stores/Index', [
            'stores' => $this->catalogQuery->listActiveStores($search),
            'categories' => $this->catalogQuery->activeCategories(),
            'filters' => [
                'q' => $search,
            ],
            'cartSummary' => $this->cartService->summaryForRequest($request),
        ]);
    }

    public function show(CatalogFilterRequest $request, Store $store): Response
    {
        if (! $store->isActive()) {
            abort(404);
        }

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
        $validated['store'] = $store->slug;

        $catalog = $this->catalogQuery->paginateCatalog($validated);

        return Inertia::render('stores/Show', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'url' => route('stores.show', ['store' => $store->slug]),
                'logoUrl' => null,
                'description' => $store->description,
                'status' => $store->status->value,
                'statusLabel' => $store->status->label(),
                'contactEmail' => $store->contact_email,
                'contactPhone' => $store->contact_phone,
                'isOfficial' => $store->is_official,
                'productCount' => (int) ($catalog['pagination']['total'] ?? 0),
            ],
            ...$catalog,
            'cartSummary' => $this->cartService->summaryForRequest($request),
        ]);
    }
}
