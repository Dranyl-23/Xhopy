<?php

namespace App\Http\Controllers\Seller;

use App\Domain\Catalog\Actions\SaveProductAction;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\CatalogPresenter;
use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Services\SecurityAuditLogger;
use App\Domain\Seller\Services\SellerWorkspaceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SaveSellerProductRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly CatalogPresenter $presenter,
        private readonly SellerWorkspaceService $workspace,
        private readonly SecurityAuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        ['store' => $store] = $this->workspace->requireApprovedWorkspace($request->user());

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:24'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $statusFilter = isset($validated['status']) ? ProductStatus::tryFrom((string) $validated['status']) : null;

        $query = Product::query()
            ->where('store_id', $store->id)
            ->with(['category', 'brand', 'store', 'variants', 'images'])
            ->latest('id');

        if ($statusFilter instanceof ProductStatus) {
            $query->where('status', $statusFilter->value);
        }

        if ($search !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search));
            $term = "%{$escaped}%";
            $query->where(function (Builder $sub) use ($term): void {
                $sub->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$term]);
            });
        }

        $paginator = $query->paginate(15)->withQueryString();

        $rawCounts = Product::query()
            ->where('store_id', $store->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('seller/products/Index', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
            ],
            'products' => collect($paginator->items())
                ->map(fn (Product $product): array => $this->presenter->managementProductRow($product))
                ->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'q' => $search,
                'status' => $statusFilter instanceof ProductStatus ? $statusFilter->value : '',
            ],
            'stats' => [
                'totalProducts' => Product::query()->where('store_id', $store->id)->count(),
                'activeProducts' => (int) ($rawCounts[ProductStatus::Active->value] ?? 0),
                'pendingReviewProducts' => (int) ($rawCounts[ProductStatus::PendingReview->value] ?? 0),
                'rejectedProducts' => (int) ($rawCounts[ProductStatus::Rejected->value] ?? 0),
                'draftProducts' => (int) ($rawCounts[ProductStatus::Draft->value] ?? 0),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        ['store' => $store] = $this->workspace->requireApprovedWorkspace($request->user());

        return Inertia::render('seller/products/Form', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
            ],
            'product' => null,
            ...$this->presenter->formReferenceData(ProductStatus::sellerSelectableCases()),
        ]);
    }

    public function store(SaveSellerProductRequest $request, SaveProductAction $action): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        ['store' => $store] = $this->workspace->requireApprovedWorkspace($actor);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        /** @var list<array<string, mixed>> $variants */
        $variants = is_array($validated['variants'] ?? null) ? array_values($validated['variants']) : [];

        $validated['store_id'] = $store->id;
        $validated['seller_name'] = $store->name;
        $validated['is_featured'] = false;

        $product = $action->execute(null, $validated, $variants);

        if ($product->status === ProductStatus::PendingReview) {
            $this->auditLogger->record(
                event: SecurityAuditEvent::SellerProductSubmittedForReview,
                user: $actor,
                actor: $actor,
                metadata: [
                    'store_id' => $store->id,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                ],
                request: $request,
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $product->status === ProductStatus::PendingReview
                ? __('Product created and submitted for Admin moderation review.')
                : __('Product saved as :status.', ['status' => $product->status->label()]),
        ]);

        return to_route('seller.products.edit', ['product' => $product->id]);
    }

    public function edit(Request $request, Product $product): Response
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        ['store' => $store] = $this->workspace->requireApprovedWorkspace($actor);
        Gate::forUser($actor)->authorize('manageOwned', $product);

        $product->load(['category', 'brand', 'store', 'moderator:id,name', 'variants', 'images']);

        return Inertia::render('seller/products/Form', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
            ],
            'product' => $this->presenter->editableProduct($product),
            ...$this->presenter->formReferenceData(ProductStatus::sellerSelectableCases()),
        ]);
    }

    public function update(
        SaveSellerProductRequest $request,
        Product $product,
        SaveProductAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        ['store' => $store] = $this->workspace->requireApprovedWorkspace($actor);
        Gate::forUser($actor)->authorize('manageOwned', $product);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        /** @var list<array<string, mixed>> $variants */
        $variants = is_array($validated['variants'] ?? null) ? array_values($validated['variants']) : [];

        $validated['store_id'] = $store->id;
        $validated['seller_name'] = $store->name;
        $validated['is_featured'] = $product->is_featured;

        $updated = $action->execute($product, $validated, $variants);

        if ($updated->status === ProductStatus::PendingReview) {
            $this->auditLogger->record(
                event: SecurityAuditEvent::SellerProductSubmittedForReview,
                user: $actor,
                actor: $actor,
                metadata: [
                    'store_id' => $store->id,
                    'product_id' => $updated->id,
                    'sku' => $updated->sku,
                ],
                request: $request,
            );
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $updated->status === ProductStatus::PendingReview
                ? __('Product changes submitted for Admin moderation review.')
                : __('Product updated (:status).', ['status' => $updated->status->label()]),
        ]);

        return to_route('seller.products.edit', ['product' => $updated->id]);
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $this->workspace->requireApprovedWorkspace($actor);
        Gate::forUser($actor)->authorize('manageOwned', $product);

        $product->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Product archived to trash.'),
        ]);

        return to_route('seller.products.index');
    }
}
