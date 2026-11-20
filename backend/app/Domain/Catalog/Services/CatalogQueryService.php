<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Enums\CampaignPlacement;
use App\Domain\Catalog\Enums\CatalogSort;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CampaignContent;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CatalogQueryService
{
    public function __construct(
        private readonly CatalogPresenter $presenter,
    ) {}

    /**
     * @return array{
     *     categories: list<array<string, mixed>>,
     *     topProducts: list<array<string, mixed>>,
     *     announcement: array<string, mixed>|null,
     *     dailyInterest: array<string, mixed>|null,
     *     hasRealCatalog: bool
     * }
     */
    public function homeCatalog(string $searchQuery = '', ?string $categorySlug = null): array
    {
        $categories = Category::query()
            ->active()
            ->withCount([
                'products' => fn (Builder $query): Builder => $query->where('status', ProductStatus::Active->value),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $productQuery = $this->basePublishedProductQuery();

        if ($categorySlug !== null && $categorySlug !== '') {
            $this->applyCategoryFilter($productQuery, $categorySlug);
        }

        if ($searchQuery !== '') {
            $this->applySearchFilter($productQuery, $searchQuery);
        }

        $topProducts = $productQuery
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $campaigns = CampaignContent::query()
            ->with('category')
            ->currentlyActive()
            ->get();

        $announcementCampaign = $campaigns->firstWhere('placement', CampaignPlacement::Announcement);
        $featuredStory = $campaigns->firstWhere('placement', CampaignPlacement::DailyInterestFeatured);
        $tiles = $campaigns->where('placement', CampaignPlacement::DailyInterestTile)->values();

        $dailyInterest = null;
        if ($featuredStory instanceof CampaignContent) {
            $dailyInterest = [
                'featured' => $this->presenter->campaignItem($featuredStory),
                'tiles' => array_values($tiles->take(4)->map(fn (CampaignContent $tile): array => $this->presenter->campaignItem($tile))->all()),
            ];
        }

        $hasRealCatalog = $categories->isNotEmpty()
            || $topProducts->isNotEmpty()
            || $announcementCampaign instanceof CampaignContent
            || $dailyInterest !== null
            || Product::query()->published()->exists();

        return [
            'categories' => array_values($categories->map(fn (Category $category): array => $this->presenter->categorySummary($category))->all()),
            'topProducts' => array_values($topProducts->map(fn (Product $product): array => $this->presenter->productSummary($product))->all()),
            'announcement' => $announcementCampaign instanceof CampaignContent
                ? [
                    'id' => (string) $announcementCampaign->id,
                    'message' => $announcementCampaign->title,
                    'linkText' => $announcementCampaign->cta_label,
                    'linkHref' => $announcementCampaign->cta_href ?? route('catalog.index'),
                ]
                : null,
            'dailyInterest' => $dailyInterest,
            'hasRealCatalog' => $hasRealCatalog,
        ];
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     category?: string|null,
     *     brand?: string|null,
     *     min_price?: float|int|string|null,
     *     max_price?: float|int|string|null,
     *     in_stock?: bool,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function paginateCatalog(array $filters): array
    {
        $query = $this->basePublishedProductQuery();

        $searchQuery = trim((string) ($filters['q'] ?? ''));
        $categorySlug = isset($filters['category']) ? trim((string) $filters['category']) : null;
        $brandSlug = isset($filters['brand']) ? trim((string) $filters['brand']) : null;
        $inStockOnly = (bool) ($filters['in_stock'] ?? false);
        $sort = CatalogSort::tryFrom((string) ($filters['sort'] ?? '')) ?? CatalogSort::Featured;
        $perPage = in_array((int) ($filters['per_page'] ?? 12), [12, 24, 48], true)
            ? (int) ($filters['per_page'] ?? 12)
            : 12;

        if ($searchQuery !== '') {
            $this->applySearchFilter($query, $searchQuery);
        }

        if ($categorySlug !== null && $categorySlug !== '') {
            $this->applyCategoryFilter($query, $categorySlug);
        }

        if ($brandSlug !== null && $brandSlug !== '') {
            $brandId = Brand::query()->active()->where('slug', $brandSlug)->value('id');
            if ($brandId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('products.brand_id', $brandId);
            }
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $minPrice = max(0, (float) $filters['min_price']);
            $query->where('products.base_price', '>=', number_format($minPrice, 2, '.', ''));
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $maxPrice = max(0, (float) $filters['max_price']);
            $query->where('products.base_price', '<=', number_format($maxPrice, 2, '.', ''));
        }

        if ($inStockOnly) {
            $query->where('products.stock_quantity', '>', 0);
        }

        match ($sort) {
            CatalogSort::Featured => $query->orderByDesc('products.is_featured')->orderByDesc('products.published_at')->orderByDesc('products.id'),
            CatalogSort::Newest => $query->orderByDesc('products.published_at')->orderByDesc('products.id'),
            CatalogSort::PriceAsc => $query->orderBy('products.base_price')->orderBy('products.id'),
            CatalogSort::PriceDesc => $query->orderByDesc('products.base_price')->orderByDesc('products.id'),
            CatalogSort::NameAsc => $query->orderBy('products.name')->orderBy('products.id'),
        };

        $paginator = $query->paginate($perPage)->withQueryString();

        $categories = Category::query()
            ->active()
            ->withCount([
                'products' => fn (Builder $catProdQuery): Builder => $catProdQuery->where('status', ProductStatus::Active->value),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $brands = Brand::query()
            ->active()
            ->orderBy('name')
            ->get();

        return [
            'products' => collect($paginator->items())
                ->map(fn (Product $product): array => $this->presenter->productSummary($product))
                ->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'categories' => $categories->map(fn (Category $category): array => $this->presenter->categorySummary($category))->all(),
            'brands' => $brands->map(fn (Brand $brand): array => $this->presenter->brandSummary($brand))->all(),
            'sortOptions' => CatalogSort::options(),
            'filters' => [
                'q' => $searchQuery,
                'category' => $categorySlug ?: null,
                'brand' => $brandSlug ?: null,
                'min_price' => isset($filters['min_price']) && $filters['min_price'] !== '' ? (string) $filters['min_price'] : '',
                'max_price' => isset($filters['max_price']) && $filters['max_price'] !== '' ? (string) $filters['max_price'] : '',
                'in_stock' => $inStockOnly,
                'sort' => $sort->value,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @return array{
     *     product: array<string, mixed>,
     *     relatedProducts: list<array<string, mixed>>
     * }|null
     */
    public function findActiveProductDetail(string $slug): ?array
    {
        /** @var Product|null $product */
        $product = $this->basePublishedProductQuery()
            ->where('products.slug', $slug)
            ->first();

        if (! $product instanceof Product) {
            return null;
        }

        $related = $this->basePublishedProductQuery()
            ->where('products.id', '!=', $product->id)
            ->where('products.category_id', $product->category_id)
            ->orderByDesc('products.is_featured')
            ->orderByDesc('products.published_at')
            ->limit(4)
            ->get();

        return [
            'product' => $this->presenter->productDetail($product),
            'relatedProducts' => array_values($related->map(fn (Product $item): array => $this->presenter->productSummary($item))->all()),
        ];
    }

    /**
     * @return Builder<Product>
     */
    private function basePublishedProductQuery(): Builder
    {
        return Product::query()
            ->published()
            ->with([
                'category',
                'brand',
                'variants' => function (HasMany $variantQuery): void {
                    $variantQuery->where('is_active', true)->orderBy('sort_order')->orderBy('id');
                },
                'images' => function (HasMany $imageQuery): void {
                    $imageQuery->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
                },
            ]);
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyCategoryFilter(Builder $query, string $categorySlug): void
    {
        $category = Category::query()->active()->where('slug', $categorySlug)->first();
        if (! $category instanceof Category) {
            $query->whereRaw('1 = 0');

            return;
        }

        $childIds = Category::query()
            ->active()
            ->where('parent_id', $category->id)
            ->pluck('id')
            ->all();

        $query->whereIn('products.category_id', array_merge([$category->id], $childIds));
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySearchFilter(Builder $query, string $searchQuery): void
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($searchQuery));
        $term = "%{$escaped}%";

        $query->where(function (Builder $sub) use ($term): void {
            $sub->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$term])
                ->orWhereRaw("LOWER(products.sku) LIKE ? ESCAPE '!'", [$term])
                ->orWhereRaw("LOWER(COALESCE(products.short_description, '')) LIKE ? ESCAPE '!'", [$term])
                ->orWhereHas('variants', function (Builder $variantQuery) use ($term): void {
                    $variantQuery->where('is_active', true)
                        ->whereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$term]);
                });
        });
    }
}
