<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Actions\SaveProductAction;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Services\CatalogPresenter;
use App\Domain\Catalog\Services\MoneyFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
            ->with(['category', 'brand', 'variants', 'images'])
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
            'products' => collect($paginator->items())->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'status' => $product->status->value,
                'statusLabel' => $product->status->label(),
                'isFeatured' => $product->is_featured,
                'categoryName' => $product->category->name,
                'brandName' => $product->brand?->name,
                'priceFormatted' => MoneyFormatter::format($product->resolvedUnitPrice(), $product->currency),
                'stockQuantity' => $product->totalAvailableStock(),
                'variantCount' => $product->variants->count(),
                'imageUrl' => $product->images->first() !== null
                    ? $this->presenter->imageUrl($product->images->first())
                    : null,
            ])->all(),
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
                'totalCategories' => Category::query()->count(),
                'totalBrands' => Brand::query()->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/catalog/ProductForm', [
            'product' => null,
            ...$this->formReferenceData(),
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
        $product->load(['category', 'brand', 'variants', 'images']);

        return Inertia::render('admin/catalog/ProductForm', [
            'product' => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'seller_name' => $product->seller_name,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'short_description' => $product->short_description ?? '',
                'description' => $product->description ?? '',
                'status' => $product->status->value,
                'is_featured' => $product->is_featured,
                'currency' => $product->currency,
                'base_price' => (string) $product->base_price,
                'compare_at_price' => $product->compare_at_price !== null ? (string) $product->compare_at_price : '',
                'stock_quantity' => $product->stock_quantity,
                'specifications' => $product->specifications ?? [],
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'title' => $variant->title,
                    'sku' => $variant->sku,
                    'price' => (string) $variant->price,
                    'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : '',
                    'stock_quantity' => $variant->stock_quantity,
                    'options' => $variant->options ?? [],
                    'is_default' => $variant->is_default,
                    'is_active' => $variant->is_active,
                ])->all(),
                'images' => $product->images->map(fn (ProductImage $image): array => [
                    'id' => $image->id,
                    'variant_id' => $image->variant_id,
                    'url' => $this->presenter->imageUrl($image),
                    'alt_text' => $image->alt_text,
                    'is_primary' => $image->is_primary,
                ])->all(),
            ],
            ...$this->formReferenceData(),
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

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return to_route('admin.catalog.index')
            ->with('success', 'Product archived to trash.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formReferenceData(): array
    {
        return [
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'is_active']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name', 'slug', 'is_active']),
            'statuses' => array_map(
                static fn (ProductStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                ProductStatus::cases(),
            ),
        ];
    }
}
