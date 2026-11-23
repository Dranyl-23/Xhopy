<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\ModerateProductAction;
use App\Domain\Catalog\Actions\SaveProductAction;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\CatalogPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateProductRequest;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:24'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $statusFilter = isset($validated['status']) ? ProductStatus::tryFrom((string) $validated['status']) : null;

        $query = Product::query()
            ->with(['category', 'brand', 'store', 'variants', 'images'])
            ->orderByRaw("CASE status WHEN 'pending_review' THEN 0 WHEN 'rejected' THEN 1 WHEN 'active' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END")
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

        return Inertia::render('admin/catalog/Index', [
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
                'totalProducts' => Product::query()->count(),
                'activeProducts' => Product::query()->where('status', ProductStatus::Active->value)->count(),
                'pendingReviewProducts' => Product::query()->where('status', ProductStatus::PendingReview->value)->count(),
                'totalCategories' => Category::query()->count(),
                'totalBrands' => Brand::query()->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/catalog/ProductForm', [
            'product' => null,
            ...$this->presenter->formReferenceData(ProductStatus::cases()),
        ]);
    }

    public function store(SaveProductRequest $request, SaveProductAction $action): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        /** @var list<array<string, mixed>> $variants */
        $variants = is_array($validated['variants'] ?? null) ? array_values($validated['variants']) : [];

        $product = $action->execute(null, $validated, $variants);

        return to_route('admin.catalog.products.edit', ['product' => $product->id])
            ->with('success', 'Product created cleanly.');
    }

    public function edit(Product $product): Response
    {
        $product->load(['category', 'brand', 'store', 'moderator:id,name', 'variants', 'images']);

        return Inertia::render('admin/catalog/ProductForm', [
            'product' => $this->presenter->editableProduct($product),
            ...$this->presenter->formReferenceData(ProductStatus::cases()),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProductAction $action): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        /** @var list<array<string, mixed>> $variants */
        $variants = is_array($validated['variants'] ?? null) ? array_values($validated['variants']) : [];

        $action->execute($product, $validated, $variants);

        return to_route('admin.catalog.products.edit', ['product' => $product->id])
            ->with('success', 'Product updated cleanly.');
    }

    public function moderate(
        ModerateProductRequest $request,
        Product $product,
        ModerateProductAction $action,
    ): RedirectResponse {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $decision = ProductStatus::from((string) $request->validated('status'));
        $reason = $request->validated('reason');

        try {
            $updated = $action->execute(
                actor: $actor,
                product: $product,
                decision: $decision,
                reason: is_string($reason) ? $reason : null,
                request: $request,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'reason' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Product status updated to :status.', ['status' => $updated->status->label()]),
        ]);

        return to_route('admin.catalog.products.edit', ['product' => $updated->id]);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return to_route('admin.catalog.index')
            ->with('success', 'Product archived to trash.');
    }
}
